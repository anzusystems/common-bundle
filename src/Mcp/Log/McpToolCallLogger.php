<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Log;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Throwable;

#[WithMonologChannel('mcp')]
final readonly class McpToolCallLogger
{
    public function __construct(
        private CurrentAnzuUserProvider $currentUserProvider,
        private LoggerInterface $logger,
        private McpLogger $mcpLogger,
    ) {
    }

    /**
     * @param array<string, mixed> $params
     */
    public function logCall(string $toolName, array $params, ?string $error, int $durationMs): void
    {
        $userId = $this->resolveCurrentUserId();
        $this->logger->info('Mcp tool call.', [
            'tool' => $toolName,
            'userId' => $userId,
            'params' => $params,
            'error' => $error,
            'durationMs' => $durationMs,
        ]);
        try {
            $this->mcpLogger->log($toolName, $params, $userId, $durationMs, $error);
        } catch (Throwable $exception) {
            $this->logger->error('Mcp tool call mongo log failed.', [
                'tool' => $toolName,
                'exception' => $exception,
            ]);
        }
    }

    public function logBackendFailure(string $toolName, Throwable $exception): void
    {
        $this->logger->error('Mcp tool call failed on backend.', [
            'tool' => $toolName,
            'exception' => $exception,
        ]);
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
}
