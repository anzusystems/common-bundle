<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Handler;

use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Mcp\Exception\McpToolAccessRejectedException;
use AnzuSystems\CommonBundle\Mcp\Exception\McpWriteRejectedException;
use AnzuSystems\CommonBundle\Mcp\Factory\McpPreviewHashFactory;
use AnzuSystems\CommonBundle\Mcp\Log\McpToolCallLogger;
use AnzuSystems\CommonBundle\Mcp\McpToolDefinitions;
use AnzuSystems\CommonBundle\Mcp\Model\McpPreviewHashAwareInterface;
use AnzuSystems\CommonBundle\Mcp\Model\McpToolSignature;
use AnzuSystems\CommonBundle\Mcp\Model\McpWritePreviewInterface;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolErrorResolver;
use AnzuSystems\CommonBundle\Mcp\Security\McpInputEnricherInterface;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use AnzuSystems\CommonBundle\Mcp\Security\McpWriteToolGuard;
use AnzuSystems\SerializerBundle\Serializer;
use Doctrine\ORM\OptimisticLockException;
use Mcp\Capability\Formatter\ToolResultFormatter;
use Mcp\Capability\Registry\ElementReference;
use Mcp\Capability\Registry\ReferenceHandlerInterface;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Result\InputRequiredResult;
use Mcp\Server\RequestContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;
use UnexpectedValueException;

/**
 * The inner handler still invokes an #[AsMcpTool] tool, so the SDK resolves the tool service and the RequestContext.
 */
final readonly class McpToolReferenceHandler implements ReferenceHandlerInterface
{
    public const string ERROR_KEY = 'error';
    public const string ERROR_RESULT_MESSAGE = 'The tool returned an error result.';
    public const string UNHANDLED_ERROR_FORMAT = 'Unhandled %s';

    private const string SESSION_ARGUMENT = '_session';
    private const string REQUEST_ARGUMENT = '_request';
    private const array SDK_ARGUMENT_KEYS = [
        self::SESSION_ARGUMENT => true,
        self::REQUEST_ARGUMENT => true,
    ];

    public function __construct(
        private ReferenceHandlerInterface $inner,
        private McpToolAccessChecker $toolAccessChecker,
        private McpWriteToolGuard $writeToolGuard,
        private McpToolDefinitions $toolDefinitions,
        private McpPreviewHashFactory $previewHashFactory,
        private RegistryInterface $registry,
        private McpToolErrorResolver $toolErrorResolver,
        private McpToolCallLogger $toolCallLogger,
        private ValidatorInterface $validator,
        private Serializer $serializer,
        private RequestStack $requestStack,
        private ?McpInputEnricherInterface $inputEnricher = null,
    ) {
    }

    public static function createErrorResult(string $error): CallToolResult
    {
        $payload = [self::ERROR_KEY => $error];

        return new CallToolResult(
            content: new ToolResultFormatter()
                ->format($payload),
            isError: true,
            structuredContent: $payload,
        );
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function handle(ElementReference $reference, array $arguments): mixed
    {
        if ($reference instanceof ToolReference) {
            $signature = $this->resolveSignature($reference);
            if ($signature instanceof McpToolSignature) {
                return $this->callTool(reference: $reference, signature: $signature, arguments: $arguments);
            }
            if (false === $this->toolAccessChecker->isToolGranted($reference->tool->name)) {
                return self::createErrorResult(McpToolAccessRejectedException::createNotGranted($reference->tool->name)->getMessage());
            }
        }

        return $this->inner->handle($reference, $arguments);
    }

    private function resolveSignature(ToolReference $reference): ?McpToolSignature
    {
        return $this->toolDefinitions->find($reference->tool->name)?->signature;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function callTool(ToolReference $reference, McpToolSignature $signature, array $arguments): CallToolResult|InputRequiredResult
    {
        $startedAt = hrtime(true);
        $toolName = $reference->tool->name;
        $params = [];
        $error = null;
        $confirmationRequest = null;

        try {
            $input = $this->resolveInput(toolName: $toolName, signature: $signature, arguments: $arguments);
            $params = is_object($input) ? $this->toArray($input) : [];
            $this->assertCallable($toolName, $input);

            $confirmationRequest = $this->guardWrite(toolName: $toolName, input: $input, arguments: $arguments);
            if ($confirmationRequest instanceof InputRequiredResult) {
                return $confirmationRequest;
            }

            $result = $this->run(
                reference: $reference,
                toolName: $toolName,
                input: $input,
                toolArguments: $this->createToolArguments(signature: $signature, input: $input, arguments: $arguments),
            );
            if ($result->isError) {
                $error = $this->resolveResultError($result);
            }

            return $result;
        } catch (Throwable $exception) {
            $error = $this->resolveErrorMessage($toolName, $exception);
            if (null === $error) {
                $error = sprintf(self::UNHANDLED_ERROR_FORMAT, $exception::class);

                throw $exception;
            }

            return self::createErrorResult($error);
        } finally {
            $this->logResult(
                toolName: $toolName,
                params: $params,
                error: $error,
                confirmationRequest: $confirmationRequest,
                startedAt: $startedAt,
                arguments: $arguments,
            );
        }
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function guardWrite(string $toolName, ?object $input, array $arguments): ?InputRequiredResult
    {
        $previewToolName = $this->toolDefinitions->getPreviewToolName($toolName);
        if (null === $previewToolName) {
            return null;
        }
        if (false === $input instanceof McpPreviewHashAwareInterface) {
            throw new UnexpectedValueException(sprintf('The input of the MCP write tool "%s" does not implement %s.', $toolName, McpPreviewHashAwareInterface::class));
        }

        return $this->writeToolGuard->guardPreview(
            toolName: $toolName,
            previewToolName: $previewToolName,
            input: $input,
            context: new RequestContext($arguments[self::SESSION_ARGUMENT], $arguments[self::REQUEST_ARGUMENT]),
            runPreview: fn (): McpWritePreviewInterface => $this->runPreview(
                writeToolName: $toolName,
                previewToolName: $previewToolName,
                input: $input,
                arguments: $arguments,
            ),
        );
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function runPreview(string $writeToolName, string $previewToolName, McpPreviewHashAwareInterface $input, array $arguments): McpWritePreviewInterface
    {
        $reference = $this->registry->getTool($previewToolName);
        $signature = $this->resolveSignature($reference)
            ?? throw new UnexpectedValueException(sprintf('The MCP preview tool "%s" is not declared by #[AsMcpTool].', $previewToolName));
        $preview = $this->inner->handle($reference, $this->createToolArguments(signature: $signature, input: $input, arguments: $arguments));
        if (false === $preview instanceof McpWritePreviewInterface) {
            throw new UnexpectedValueException(sprintf('The MCP preview tool "%s" returned "%s" instead of %s.', $previewToolName, get_debug_type($preview), McpWritePreviewInterface::class));
        }
        $this->signPreview(writeToolName: $writeToolName, input: $input, preview: $preview);

        return $preview;
    }

    private function signPreview(string $writeToolName, object $input, McpWritePreviewInterface $preview): void
    {
        $preview->previewHash = $preview->writable
            ? $this->previewHashFactory->create(writeToolName: $writeToolName, input: $input, currentState: $preview->currentState)
            : null;
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $arguments
     */
    private function logResult(
        string $toolName,
        array $params,
        ?string $error,
        ?InputRequiredResult $confirmationRequest,
        int $startedAt,
        array $arguments,
    ): void {
        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
        if ($confirmationRequest instanceof InputRequiredResult) {
            $this->toolCallLogger->logConfirmationAsked(toolName: $toolName, params: $params, durationMs: $durationMs);

            return;
        }
        $this->toolCallLogger->logCall(
            toolName: $toolName,
            params: $params,
            error: $error,
            durationMs: $durationMs,
            session: $arguments[self::SESSION_ARGUMENT],
        );
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function resolveInput(string $toolName, McpToolSignature $signature, array $arguments): ?object
    {
        $inputClass = $signature->inputClass;
        if (null === $inputClass) {
            return null;
        }
        $input = $this->serializer->fromArray(array_diff_key($arguments, self::SDK_ARGUMENT_KEYS), $inputClass);
        if (false === is_object($input)) {
            throw new UnexpectedValueException(sprintf('The MCP tool "%s" input is not an object.', $toolName));
        }

        return $input;
    }

    private function assertCallable(string $toolName, ?object $input): void
    {
        if (false === $this->toolAccessChecker->isToolPermissionGranted($toolName)) {
            throw McpToolAccessRejectedException::createNotGranted($toolName);
        }
        $isWriteTool = $this->toolAccessChecker->isWriteTool($toolName);
        if ($isWriteTool) {
            $this->writeToolGuard->assertWriteGranted($toolName);
        }
        if (is_object($input)) {
            $this->assertValid($input);
            $this->assertInputGranted($toolName, $input);
        }
        if ($isWriteTool) {
            $this->writeToolGuard->assertWritable($toolName);
        }
    }

    /**
     * @param array<string, mixed> $toolArguments
     */
    private function run(ToolReference $reference, string $toolName, ?object $input, array $toolArguments): CallToolResult
    {
        if (false === $this->toolAccessChecker->isWriteTool($toolName)) {
            return $this->createReadResult(reference: $reference, toolName: $toolName, input: $input, toolArguments: $toolArguments);
        }
        $this->includeInAuditLogs();
        try {
            $result = $this->inner->handle($reference, $toolArguments);
            if ($result instanceof CallToolResult && $result->isError) {
                $this->revertFailedWrite($input);

                return $result;
            }
            $this->writeToolGuard->markWritten();

            return $this->createResult($toolName, $result);
        } catch (OptimisticLockException) {
            $this->revertFailedWrite($input);

            throw McpWriteRejectedException::createPreviewStale((string) $this->toolDefinitions->getPreviewToolName($toolName));
        } catch (Throwable $exception) {
            $this->revertFailedWrite($input);

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $toolArguments
     */
    private function createReadResult(ToolReference $reference, string $toolName, ?object $input, array $toolArguments): CallToolResult
    {
        $result = $this->inner->handle($reference, $toolArguments);
        $writeToolName = $this->toolDefinitions->find($toolName)?->previewOf;
        if ($result instanceof McpWritePreviewInterface && is_string($writeToolName) && is_object($input)) {
            $this->signPreview(writeToolName: $writeToolName, input: $input, preview: $result);
        }

        return $this->createResult($toolName, $result);
    }

    private function includeInAuditLogs(): void
    {
        $request = $this->requestStack->getMainRequest();
        if ($request instanceof Request) {
            AuditLogResourceHelper::includeInAuditLogs($request);
        }
    }

    private function revertFailedWrite(?object $input): void
    {
        if ($input instanceof McpPreviewHashAwareInterface) {
            $this->writeToolGuard->releasePreviewHash($input);
        }
        $request = $this->requestStack->getMainRequest();
        if ($request instanceof Request && false === AuditLogResourceHelper::hasResourceId($request)) {
            AuditLogResourceHelper::excludeFromAuditLogs($request);
        }
    }

    /**
     * The SDK maps the arguments to the __invoke() parameters by name and builds the RequestContext from its own keys.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function createToolArguments(McpToolSignature $signature, ?object $input, array $arguments): array
    {
        $toolArguments = array_intersect_key($arguments, self::SDK_ARGUMENT_KEYS);
        if (is_string($signature->inputParameterName)) {
            $toolArguments[$signature->inputParameterName] = $input;
        }

        return $toolArguments;
    }

    private function assertInputGranted(string $toolName, object $input): void
    {
        if (false === $this->toolAccessChecker->isInputGranted($toolName, $input)) {
            throw new AccessDeniedException();
        }
        if (is_string($this->toolDefinitions->find($toolName)?->inputPermission)) {
            $this->inputEnricher?->enrich($input);
        }
    }

    private function assertValid(object $input): void
    {
        $violations = $this->validator->validate($input);
        if (0 === $violations->count()) {
            return;
        }

        throw McpToolAccessRejectedException::createInvalidArguments($this->toolErrorResolver->formatViolations($violations));
    }

    private function resolveErrorMessage(string $toolName, Throwable $exception): ?string
    {
        $message = $this->toolErrorResolver->resolveRejectionMessage($exception);
        if (is_string($message)) {
            return $message;
        }
        $message = $this->toolErrorResolver->resolveBackendMessage($exception);
        if (is_string($message)) {
            $this->toolCallLogger->logBackendFailure($toolName, $exception);
        }

        return $message;
    }

    private function resolveResultError(CallToolResult $result): string
    {
        $error = is_array($result->structuredContent) ? ($result->structuredContent[self::ERROR_KEY] ?? null) : null;

        return is_string($error) ? $error : self::ERROR_RESULT_MESSAGE;
    }

    private function createResult(string $toolName, mixed $result): CallToolResult
    {
        if ($result instanceof CallToolResult) {
            return $result;
        }
        if (false === is_object($result)) {
            throw new UnexpectedValueException(sprintf('The MCP tool "%s" returned "%s" instead of an object.', $toolName, get_debug_type($result)));
        }

        return $this->createSuccessResult($this->toArray($result));
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(object $data): array
    {
        return (array) $this->serializer->toArray($data);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createSuccessResult(array $payload): CallToolResult
    {
        return new CallToolResult(
            new ToolResultFormatter()
                ->format($payload),
            structuredContent: $payload,
        );
    }
}
