<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Log;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use Mcp\Server\Session\SessionInterface;
use Mcp\Server\Stateless\RequestMeta;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Throwable;

#[WithMonologChannel('mcp')]
final readonly class McpToolCallLogger
{
    public const string CALL_LOG_MESSAGE = 'Mcp tool call.';
    public const string CONFIRMATION_ASKED_LOG_MESSAGE = 'Mcp tool confirmation asked.';

    private const string SESSION_CLIENT_INFO_KEY = 'client_info';
    private const string CLIENT_NAME_KEY = 'name';
    private const string CONTEXT_TOOL = 'tool';
    private const string CONTEXT_USER_ID = 'userId';
    private const string CONTEXT_PARAMS = 'params';
    private const string CONTEXT_DURATION_MS = 'durationMs';
    private const string CONTEXT_EXCEPTION = 'exception';

    public function __construct(
        private CurrentAnzuUserProvider $currentUserProvider,
        private LoggerInterface $logger,
        private McpLogger $mcpLogger,
    ) {
    }

    /**
     * @param array<string, mixed> $params
     */
    public function logCall(string $toolName, array $params, ?string $error, int $durationMs, SessionInterface $session): void
    {
        $userId = $this->resolveCurrentUserId();
        $client = $this->resolveClientName($session);
        $this->logger->info(self::CALL_LOG_MESSAGE, [
            self::CONTEXT_TOOL => $toolName,
            self::CONTEXT_USER_ID => $userId,
            'client' => $client,
            self::CONTEXT_PARAMS => $params,
            'error' => $error,
            self::CONTEXT_DURATION_MS => $durationMs,
        ]);
        try {
            $this->mcpLogger->log(
                tool: $toolName,
                params: $params,
                userId: $userId,
                durationMs: $durationMs,
                error: $error,
                client: $client,
            );
        } catch (Throwable $exception) {
            $this->logger->error('Mcp tool call mongo log failed.', [
                self::CONTEXT_TOOL => $toolName,
                self::CONTEXT_EXCEPTION => $exception,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public function logConfirmationAsked(string $toolName, array $params, int $durationMs): void
    {
        $this->logger->info(self::CONFIRMATION_ASKED_LOG_MESSAGE, [
            self::CONTEXT_TOOL => $toolName,
            self::CONTEXT_USER_ID => $this->resolveCurrentUserId(),
            self::CONTEXT_PARAMS => $params,
            self::CONTEXT_DURATION_MS => $durationMs,
        ]);
    }

    public function logBackendFailure(string $toolName, Throwable $exception): void
    {
        $this->logger->error('Mcp tool call failed on backend.', [
            self::CONTEXT_TOOL => $toolName,
            self::CONTEXT_EXCEPTION => $exception,
        ]);
    }

    private function resolveClientName(SessionInterface $session): ?string
    {
        $meta = $session->get(RequestMeta::class);
        if ($meta instanceof RequestMeta) {
            return $meta->clientInfo?->name;
        }
        $clientInfo = $session->get(self::SESSION_CLIENT_INFO_KEY);
        $name = is_array($clientInfo) ? ($clientInfo[self::CLIENT_NAME_KEY] ?? null) : null;

        return is_string($name) ? $name : null;
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
