<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Mcp\Exception\McpToolInputException;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogger;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Entity\Interfaces\BaseIdentifiableInterface;
use AnzuSystems\SerializerBundle\Serializer;
use Closure;
use LogicException;
use Mcp\Capability\Formatter\ToolResultFormatter;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\RequestContext;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;
use UnexpectedValueException;

#[WithMonologChannel('mcp')]
final readonly class McpToolExecutor
{
    public const string ERROR_KEY = 'error';
    public const string WARNINGS_KEY = 'warnings';

    private const string TOOL_ACCESS_DENIED_MESSAGE = 'Access denied — the current MCP user is not allowed to use the tool "%s".';
    private const string WRITE_TOOL_NOT_GRANTED_MESSAGE = 'Access denied — the write tool "%s" is not granted to this request.';
    private const string ACCESS_DENIED_MESSAGE = 'Access denied — the current MCP user is not allowed to access the requested resource.';
    private const string READ_ONLY_MODE_MESSAGE = 'The system is in read-only mode, changes are temporarily disabled — retry later.';
    private const string WRITE_TOOL_DONE_MESSAGE = 'A write tool already ran in this request — send the call of "%s" in a separate request.';
    private const string WRITE_TOOL_DONE_ATTR = '_mcp_write_tool_done';
    private const string INVALID_ARGUMENTS_PREFIX = 'Invalid arguments: ';
    private const string VIOLATION_FORMAT = '%s: %s';
    private const string VIOLATION_SEPARATOR = ' ';

    /**
     * @param array<class-string<Throwable>, string> $toolErrorExceptions
     * @param array<string, class-string> $toolInputClasses
     */
    public function __construct(
        private CurrentAnzuUserProvider $currentUserProvider,
        private LoggerInterface $logger,
        private McpLogger $mcpLogger,
        private McpToolAccessChecker $toolAccessChecker,
        private ValidatorInterface $validator,
        private Serializer $serializer,
        private RequestStack $requestStack,
        private array $toolErrorExceptions = [],
        private array $toolInputClasses = [],
    ) {
    }

    public function addAuditLogResource(BaseIdentifiableInterface $entity): void
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request) {
            return;
        }
        AuditLogResourceHelper::addResourceByEntity($request, $entity);
    }

    /**
     * The callback gets the input DTO of the tool's #[McpToolInput] (or nothing for a tool without input) and returns
     * the result object or array.
     */
    public function execute(string $toolName, ?RequestContext $context, Closure $callback): CallToolResult
    {
        $startedAt = hrtime(true);
        $params = [];
        $error = null;
        $auditedRequest = null;

        try {
            $input = $this->resolveInput($toolName, $context);
            $params = null === $input ? [] : $this->toArray($input);
            if (false === $this->toolAccessChecker->isToolPermissionGranted($toolName)) {
                $error = sprintf(self::TOOL_ACCESS_DENIED_MESSAGE, $toolName);

                return $this->createErrorResult($error);
            }
            if (false === $this->toolAccessChecker->isWriteGranted($toolName)) {
                $error = sprintf(self::WRITE_TOOL_NOT_GRANTED_MESSAGE, $toolName);

                return $this->createErrorResult($error);
            }
            $validationError = $this->resolveValidationError($input);
            if (is_string($validationError)) {
                $error = $validationError;

                return $this->createErrorResult($error);
            }
            if (false === $this->toolAccessChecker->isWriteTool($toolName)) {
                return $this->createSuccessResult($this->resolveResult($callback($input)));
            }
            if (AnzuApp::isReadOnlyMode()) {
                $error = self::READ_ONLY_MODE_MESSAGE;

                return $this->createErrorResult($error);
            }
            $mainRequest = $this->requestStack->getMainRequest();
            if (true === $mainRequest?->attributes->has(self::WRITE_TOOL_DONE_ATTR)) {
                $error = sprintf(self::WRITE_TOOL_DONE_MESSAGE, $toolName);

                return $this->createErrorResult($error);
            }
            if ($mainRequest instanceof Request) {
                AuditLogResourceHelper::includeInAuditLogs($mainRequest);
                $auditedRequest = $mainRequest;
            }
            $result = $callback($input);
            $mainRequest?->attributes->set(self::WRITE_TOOL_DONE_ATTR, true);

            return $this->createSuccessResult($this->resolveResult($result));
        } catch (McpToolInputException $exception) {
            $error = $exception->getMessage();

            return $this->createErrorResult($error);
        } catch (ValidationException $exception) {
            $error = $this->resolveValidationExceptionError($exception);

            return $this->createErrorResult($error);
        } catch (AccessDeniedException) {
            $error = self::ACCESS_DENIED_MESSAGE;

            return $this->createErrorResult($error);
        } catch (Throwable $exception) {
            $message = $this->resolveToolErrorMessage($exception);
            if (null === $message) {
                $error = sprintf('Unhandled %s', $exception::class);

                throw $exception;
            }
            $this->logger->error('Mcp tool call failed on backend.', [
                'tool' => $toolName,
                'exception' => $exception,
            ]);
            $error = $message;

            return $this->createErrorResult($error);
        } finally {
            if (is_string($error) && $auditedRequest instanceof Request && false === AuditLogResourceHelper::hasResourceId($auditedRequest)) {
                AuditLogResourceHelper::excludeFromAuditLogs($auditedRequest);
            }
            $userId = $this->resolveCurrentUserId();
            $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

            $this->logger->info('Mcp tool call.', [
                'tool' => $toolName,
                'userId' => $userId,
                'params' => $params,
                'error' => $error,
                'durationMs' => $durationMs,
            ]);
            $this->logToMongo($toolName, $params, $userId, $durationMs, $error);
        }
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

    private function resolveInput(string $toolName, ?RequestContext $context): ?object
    {
        if (null === $context) {
            return null;
        }
        $inputClass = $this->toolInputClasses[$toolName] ?? throw new LogicException(sprintf(
            'The MCP tool "%s" passes its RequestContext, but has no #[McpToolInput].',
            $toolName,
        ));
        $callToolRequest = $context->getRequest();
        if (false === $callToolRequest instanceof CallToolRequest) {
            throw new UnexpectedValueException(sprintf('Unsupported MCP request "%s".', get_debug_type($callToolRequest)));
        }
        $input = $this->serializer->fromArray($callToolRequest->arguments, $inputClass);
        if (false === is_object($input)) {
            throw new UnexpectedValueException(sprintf('The MCP tool "%s" input is not an object.', $toolName));
        }

        return $input;
    }

    private function resolveValidationError(?object $input): ?string
    {
        if (null === $input) {
            return null;
        }
        $violations = $this->validator->validate($input);
        if (0 === $violations->count()) {
            return null;
        }
        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = sprintf(self::VIOLATION_FORMAT, $violation->getPropertyPath(), $violation->getMessage());
        }

        return $this->formatInvalidArguments($messages);
    }

    private function resolveValidationExceptionError(ValidationException $exception): string
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
    private function resolveResult(object|array $result): array
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

    private function resolveCurrentUserId(): ?int
    {
        try {
            return $this->currentUserProvider->getCurrentUser()
                ->getId();
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveToolErrorMessage(Throwable $exception): ?string
    {
        foreach ($this->toolErrorExceptions as $exceptionClass => $message) {
            if ($exception instanceof $exceptionClass) {
                return $message;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function logToMongo(string $toolName, array $params, ?int $userId, int $durationMs, ?string $error): void
    {
        try {
            $this->mcpLogger->log($toolName, $params, $userId, $durationMs, $error);
        } catch (Throwable $exception) {
            $this->logger->error('Mcp tool call mongo log failed.', [
                'tool' => $toolName,
                'exception' => $exception,
            ]);
        }
    }
}
