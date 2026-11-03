<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Handler;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Mcp\Exception\McpToolCallRejectedException;
use AnzuSystems\CommonBundle\Mcp\Exception\McpToolInputException;
use AnzuSystems\CommonBundle\Mcp\Log\McpToolCallLogger;
use AnzuSystems\CommonBundle\Mcp\Model\McpToolSignature;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolErrorResolver;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use AnzuSystems\CommonBundle\Mcp\Security\McpWriteToolGuard;
use AnzuSystems\SerializerBundle\Serializer;
use Mcp\Capability\Formatter\ToolResultFormatter;
use Mcp\Capability\Registry\ElementReference;
use Mcp\Capability\Registry\ReferenceHandlerInterface;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Schema\Result\CallToolResult;
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
    public const string WARNINGS_KEY = 'warnings';
    public const string TOOL_ACCESS_DENIED_MESSAGE = 'Access denied — the current MCP user is not allowed to use the tool "%s".';
    public const string ACCESS_DENIED_MESSAGE = 'Access denied — the current MCP user is not allowed to access the requested resource.';
    public const string INVALID_ARGUMENTS_PREFIX = 'Invalid arguments: ';
    public const string ERROR_RESULT_MESSAGE = 'The tool returned an error result.';
    public const string UNHANDLED_ERROR_FORMAT = 'Unhandled %s';

    private const string INVOKE_METHOD = '__invoke';
    private const array SDK_ARGUMENT_KEYS = [
        '_session' => true,
        '_request' => true,
    ];
    private const string VIOLATION_FORMAT = '%s: %s';
    private const string VIOLATION_SEPARATOR = ' ';

    /**
     * @param array<class-string, McpToolSignature> $toolSignatures built by McpToolReferenceHandlerCompilerPass
     */
    public function __construct(
        private ReferenceHandlerInterface $inner,
        private McpToolAccessChecker $toolAccessChecker,
        private McpWriteToolGuard $writeToolGuard,
        private McpToolErrorResolver $toolErrorResolver,
        private McpToolCallLogger $toolCallLogger,
        private ValidatorInterface $validator,
        private Serializer $serializer,
        private array $toolSignatures = [],
    ) {
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function handle(ElementReference $reference, array $arguments): mixed
    {
        if ($reference instanceof ToolReference) {
            $signature = $this->resolveSignature($reference);
            if ($signature instanceof McpToolSignature) {
                return $this->callTool($reference, $signature, $arguments);
            }
        }

        return $this->inner->handle($reference, $arguments);
    }

    private function resolveSignature(ToolReference $reference): ?McpToolSignature
    {
        $handler = $reference->handler;
        if (false === is_array($handler) || self::INVOKE_METHOD !== $handler[1] || false === is_string($handler[0])) {
            return null;
        }

        return $this->toolSignatures[$handler[0]] ?? null;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function callTool(ToolReference $reference, McpToolSignature $signature, array $arguments): CallToolResult
    {
        $startedAt = hrtime(true);
        $toolName = $reference->tool->name;
        $params = [];
        $error = null;

        try {
            $input = $this->resolveInput($toolName, $signature, $arguments);
            $params = is_object($input) ? $this->toArray($input) : [];
            $this->assertCallable($toolName, $input);

            $result = $this->run($reference, $toolName, $this->createToolArguments($signature, $input, $arguments));
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

            return $this->createErrorResult($error);
        } finally {
            $this->toolCallLogger->logCall(
                toolName: $toolName,
                params: $params,
                error: $error,
                durationMs: (int) round((hrtime(true) - $startedAt) / 1_000_000),
            );
        }
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
            throw new McpToolCallRejectedException(sprintf(self::TOOL_ACCESS_DENIED_MESSAGE, $toolName));
        }
        $isWriteTool = $this->toolAccessChecker->isWriteTool($toolName);
        if ($isWriteTool) {
            $this->writeToolGuard->assertWriteGranted($toolName);
        }
        if (is_object($input)) {
            $this->assertValid($input);
        }
        if ($isWriteTool) {
            $this->writeToolGuard->assertWritable($toolName);
        }
    }

    /**
     * @param array<string, mixed> $toolArguments
     */
    private function run(ToolReference $reference, string $toolName, array $toolArguments): CallToolResult
    {
        if (false === $this->toolAccessChecker->isWriteTool($toolName)) {
            return $this->createResult($toolName, $this->inner->handle($reference, $toolArguments));
        }
        $this->writeToolGuard->includeInAuditLogs();
        try {
            $result = $this->inner->handle($reference, $toolArguments);
            if ($result instanceof CallToolResult && $result->isError) {
                $this->writeToolGuard->excludeFailedWriteFromAuditLogs();

                return $result;
            }
            $this->writeToolGuard->markWritten();

            return $this->createResult($toolName, $result);
        } catch (Throwable $exception) {
            $this->writeToolGuard->excludeFailedWriteFromAuditLogs();

            throw $exception;
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

    private function assertValid(object $input): void
    {
        $violations = $this->validator->validate($input);
        if (0 === $violations->count()) {
            return;
        }
        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = sprintf(self::VIOLATION_FORMAT, $violation->getPropertyPath(), $violation->getMessage());
        }

        throw new McpToolCallRejectedException($this->formatInvalidArguments($messages));
    }

    private function resolveErrorMessage(string $toolName, Throwable $exception): ?string
    {
        return match (true) {
            $exception instanceof McpToolCallRejectedException,
            $exception instanceof McpToolInputException => $exception->getMessage(),
            $exception instanceof ValidationException => $this->formatValidationException($exception),
            $exception instanceof AccessDeniedException => self::ACCESS_DENIED_MESSAGE,
            default => $this->resolveBackendErrorMessage($toolName, $exception),
        };
    }

    private function resolveBackendErrorMessage(string $toolName, Throwable $exception): ?string
    {
        $message = $this->toolErrorResolver->resolveMessage($exception);
        if (is_string($message)) {
            $this->toolCallLogger->logBackendFailure($toolName, $exception);
        }

        return $message;
    }

    private function formatValidationException(ValidationException $exception): string
    {
        $messages = [];
        foreach ($exception->getFormattedErrors() as $field => $fieldMessages) {
            foreach ($fieldMessages as $fieldMessage) {
                $messages[] = sprintf(self::VIOLATION_FORMAT, $field, $fieldMessage);
            }
        }

        return $this->formatInvalidArguments($messages);
    }

    /**
     * @param list<string> $messages
     */
    private function formatInvalidArguments(array $messages): string
    {
        return self::INVALID_ARGUMENTS_PREFIX . implode(self::VIOLATION_SEPARATOR, $messages);
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

    private function createErrorResult(string $error): CallToolResult
    {
        $payload = [self::ERROR_KEY => $error];

        return new CallToolResult(
            new ToolResultFormatter()
                ->format($payload),
            isError: true,
            structuredContent: $payload,
        );
    }
}
