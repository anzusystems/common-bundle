<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\Contracts\AnzuApp;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final readonly class McpRateLimiter
{
    public function __construct(
        private RateLimiterFactory $mcpLimiter,
        private CurrentAnzuUserProvider $currentUserProvider,
    ) {
    }

    /**
     * @throws AccessDeniedHttpException
     * @throws TooManyRequestsHttpException
     */
    public function checkRateLimit(): void
    {
        $userId = (int) $this->currentUserProvider->getCurrentUser()
            ->getId();
        if (AnzuApp::getUserIdAnonymous() === $userId) {
            throw new AccessDeniedHttpException('Anonymous access to the MCP endpoint is not allowed.');
        }

        $limiter = $this->mcpLimiter->create((string) $userId);
        $limit = $limiter->consume();
        if ($limit->isAccepted()) {
            return;
        }

        $retryAfter = $limit->getRetryAfter();

        throw new TooManyRequestsHttpException(
            $retryAfter->getTimestamp(),
            'Too many requests',
            headers: [
                'X-RateLimit-Limit' => (string) $limit->getLimit(),
                'X-RateLimit-Remaining' => (string) $limit->getRemainingTokens(),
                'X-RateLimit-Reset' => (string) $retryAfter->getTimestamp(),
                'Retry-After' => (string) max(0, $retryAfter->getTimestamp() - time()),
            ],
        );
    }
}
