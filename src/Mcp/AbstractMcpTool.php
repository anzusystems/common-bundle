<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Exception\McpToolCallRejectedException;
use AnzuSystems\CommonBundle\Mcp\Exception\McpToolInputException;
use AnzuSystems\CommonBundle\Mcp\Log\McpToolCallLogger;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolErrorResolver;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use AnzuSystems\CommonBundle\Mcp\Security\McpWriteToolGuard;
use AnzuSystems\Contracts\Entity\Interfaces\BaseIdentifiableInterface;
use AnzuSystems\SerializerBundle\Serializer;
use LogicException;
use Mcp\Capability\Formatter\ToolResultFormatter;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\RequestContext;
use ReflectionClass;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;
use UnexpectedValueException;

/**
 * The tool declares __invoke itself, because McpBundle reads #[AsMcpTool] from the class that declares __invoke.
 *
 * @template TInput of object
 */
abstract readonly class AbstractMcpTool
{
    public const string ERROR_KEY = 'error';
    public const string WARNINGS_KEY = 'warnings';
    public const string TOOL_ACCESS_DENIED_MESSAGE = 'Access denied — the current MCP user is not allowed to use the tool "%s".';
    public const string ACCESS_DENIED_MESSAGE = 'Access denied — the current MCP user is not allowed to access the requested resource.';
    public const string INVALID_ARGUMENTS_PREFIX = 'Invalid arguments: ';
    public const string UNHANDLED_ERROR_FORMAT = 'Unhandled %s';

    private const string VIOLATION_FORMAT = '%s: %s';
    private const string VIOLATION_SEPARATOR = ' ';

    private McpToolAccessChecker $toolAccessChecker;
    private McpWriteToolGuard $writeToolGuard;
    private McpToolErrorResolver $toolErrorResolver;
    private McpToolCallLogger $toolCallLogger;
    private ValidatorInterface $validator;
    private Serializer $serializer;

    abstract public function __invoke(RequestContext $context): CallToolResult;

    #[Required]
    public function setToolAccessChecker(
        McpToolAccessChecker $toolAccessChecker
    ): void {
        $this->toolAccessChecker = $toolAccessChecker;
    }

    #[Required]
    public function setWriteToolGuard(
        McpWriteToolGuard $writeToolGuard
    ): void {
        $this->writeToolGuard = $writeToolGuard;
    }

    #[Required]
    public function setToolErrorResolver(
        McpToolErrorResolver $toolErrorResolver
    ): void {
        $this->toolErrorResolver = $toolErrorResolver;
    }

    #[Required]
    public function setToolCallLogger(
        McpToolCallLogger $toolCallLogger
    ): void {
        $this->toolCallLogger = $toolCallLogger;
    }

    #[Required]
    public function setValidator(
        ValidatorInterface $validator
    ): void {
        $this->validator = $validator;
    }

    #[Required]
    public function setSerializer(
        Serializer $serializer
    ): void {
        $this->serializer = $serializer;
    }

    final protected function execute(RequestContext $context): CallToolResult
    {
        $startedAt = hrtime(true);
        $tool = $this->getTool();
        $toolName = (string) $tool->name;
        $params = [];
        $error = null;

        try {
            /** @var TInput $input */
            $input = $this->resolveInput($tool, $context);
            $params = $this->toArray($input);
            $this->assertCallable($input);

            return $this->createSuccessResult($this->run($input));
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
     * @param TInput $input
     *
     * @return object|array<string, mixed>
     */
    abstract protected function handle(object $input): object|array;

    /**
     * @param TInput $input
     */
    protected function assertCallable(object $input): void
    {
        $toolName = $this->getToolName();
        if (false === $this->toolAccessChecker->isToolPermissionGranted($toolName)) {
            throw new McpToolCallRejectedException(sprintf(self::TOOL_ACCESS_DENIED_MESSAGE, $toolName));
        }
        $isWriteTool = $this->isWriteTool();
        if ($isWriteTool) {
            $this->writeToolGuard->assertWriteGranted($toolName);
        }
        $this->assertValid($input);
        if ($isWriteTool) {
            $this->writeToolGuard->assertWritable($toolName);
        }
    }

    /**
     * @param TInput $input
     *
     * @return array<string, mixed>
     */
    protected function run(object $input): array
    {
        if (false === $this->isWriteTool()) {
            return $this->toPayload($this->handle($input));
        }
        $writeToolGuard = $this->writeToolGuard;
        $writeToolGuard->includeInAuditLogs();
        try {
            $result = $this->handle($input);
            $writeToolGuard->markWritten();

            return $this->toPayload($result);
        } catch (Throwable $exception) {
            $writeToolGuard->excludeFailedWriteFromAuditLogs();

            throw $exception;
        }
    }

    protected function addAuditLogResource(BaseIdentifiableInterface $entity): void
    {
        $this->writeToolGuard->addAuditLogResource($entity);
    }

    private function getTool(): AsMcpTool
    {
        $attributes = new ReflectionClass($this)
            ->getAttributes(AsMcpTool::class);
        if (empty($attributes)) {
            throw new LogicException(sprintf('The MCP tool "%s" extends %s, so it declares #[AsMcpTool].', static::class, self::class));
        }

        return $attributes[0]->newInstance();
    }

    private function getToolName(): string
    {
        return (string) $this->getTool()
            ->name;
    }

    private function isWriteTool(): bool
    {
        return $this->toolAccessChecker->isWriteTool($this->getToolName());
    }

    private function resolveInput(AsMcpTool $tool, RequestContext $context): object
    {
        if (null === $tool->input) {
            return (object) [];
        }
        $callToolRequest = $context->getRequest();
        if (false === $callToolRequest instanceof CallToolRequest) {
            throw new UnexpectedValueException(sprintf('Unsupported MCP request "%s".', get_debug_type($callToolRequest)));
        }
        $input = $this->serializer->fromArray($callToolRequest->arguments, $tool->input);
        if (false === is_object($input)) {
            throw new UnexpectedValueException(sprintf('The MCP tool "%s" input is not an object.', $tool->name));
        }

        return $input;
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

    /**
     * @param object|array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private function toPayload(object|array $result): array
    {
        if (is_array($result)) {
            return $result;
        }

        return $this->toArray($result);
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
