<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Mcp\Exception\McpToolInputException;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogger;
use Closure;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Throwable;

#[WithMonologChannel('mcp')]
final readonly class McpToolExecutor
{
    public const string ERROR_KEY = 'error';

    /**
     * @param array<class-string<Throwable>, string> $toolErrorExceptions
     */
    public function __construct(
        private CurrentAnzuUserProvider $currentUserProvider,
        private LoggerInterface $logger,
        private McpLogger $mcpLogger,
        private array $toolErrorExceptions = [],
    ) {
    }

    /**
     * @param array<string, mixed> $params
     * @param Closure(): array<string, mixed> $callback
     *
     * @return array<string, mixed>
     */
    public function execute(string $toolName, array $params, Closure $callback): array
    {
        $startedAt = hrtime(true);
        $error = null;

        try {
            return $callback();
        } catch (McpToolInputException $exception) {
            $error = $exception->getMessage();

            return [self::ERROR_KEY => $error];
        } catch (AccessDeniedException) {
            $error = 'Access denied — the current MCP user is not allowed to use the requested filters.';

            return [self::ERROR_KEY => $error];
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

            return [self::ERROR_KEY => $error];
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
