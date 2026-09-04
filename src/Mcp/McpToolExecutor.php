<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Mcp\Exception\McpToolInputException;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogger;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use AnzuSystems\SerializerBundle\Serializer;
use Closure;
use Mcp\Capability\Formatter\ToolResultFormatter;
use Mcp\Schema\Result\CallToolResult;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;

#[WithMonologChannel('mcp')]
final readonly class McpToolExecutor
{
    public const string ERROR_KEY = 'error';
    public const string WARNINGS_KEY = 'warnings';

    private const string TOOL_ACCESS_DENIED_MESSAGE = 'Access denied — the current MCP user is not allowed to use the tool "%s".';
    private const string INVALID_ARGUMENTS_PREFIX = 'Invalid arguments: ';
    private const string VIOLATION_FORMAT = '%s: %s';
    private const string VIOLATION_SEPARATOR = ' ';

    /**
     * @param array<class-string<Throwable>, string> $toolErrorExceptions
     */
    public function __construct(
        private CurrentAnzuUserProvider $currentUserProvider,
        private LoggerInterface $logger,
        private McpLogger $mcpLogger,
        private McpToolAccessChecker $toolAccessChecker,
        private ValidatorInterface $validator,
        private Serializer $serializer,
        private array $toolErrorExceptions = [],
    ) {
    }

    /**
     * @param Closure(): (object|array<string, mixed>) $callback
     */
    public function execute(string $toolName, ?object $request, Closure $callback): CallToolResult
    {
        $startedAt = hrtime(true);
        $params = null === $request ? [] : $this->toArray($request);
        $error = null;

        try {
            if (false === $this->toolAccessChecker->isToolGranted($toolName)) {
                $error = sprintf(self::TOOL_ACCESS_DENIED_MESSAGE, $toolName);

                return $this->createErrorResult($error);
            }
            $validationError = $this->resolveValidationError($request);
            if (is_string($validationError)) {
                $error = $validationError;

                return $this->createErrorResult($error);
            }

            return $this->createSuccessResult($this->resolveResult($callback()));
        } catch (McpToolInputException $exception) {
            $error = $exception->getMessage();

            return $this->createErrorResult($error);
        } catch (AccessDeniedException) {
            $error = 'Access denied — the current MCP user is not allowed to use the requested filters.';

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

    private function resolveValidationError(?object $request): ?string
    {
        if (null === $request) {
            return null;
        }
        $violations = $this->validator->validate($request);
        if (0 === $violations->count()) {
            return null;
        }
        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = sprintf(self::VIOLATION_FORMAT, $violation->getPropertyPath(), $violation->getMessage());
        }

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
