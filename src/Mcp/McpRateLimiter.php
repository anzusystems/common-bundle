<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Helper\StringHelper;
use AnzuSystems\Contracts\AnzuApp;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

final readonly class McpRateLimiter
{
    private const string LIMITER_ID = 'mcp';
    private const string LIMITER_POLICY = 'sliding_window';
    private const int LIMIT_MIN = 1;
    private const int MESSAGES_MIN = 1;

    public function __construct(
        private int $limit,
        private string $interval,
        private StorageInterface $storage,
        private CurrentAnzuUserProvider $currentUserProvider,
        private Security $security,
        private ?string $elevatedRole = null,
        private ?int $elevatedLimit = null,
    ) {
    }

    /**
     * @throws AccessDeniedHttpException
     * @throws TooManyRequestsHttpException
     */
    public function checkRateLimit(int $messages = self::MESSAGES_MIN): void
    {
        $userId = (int) $this->currentUserProvider->getCurrentUser()
            ->getId();
        if (AnzuApp::getUserIdAnonymous() === $userId) {
            throw new AccessDeniedHttpException('Anonymous access to the MCP endpoint is not allowed.');
        }

        $limit = $this->resolveLimit();
        $rateLimit = $this->createLimiter((string) $userId, $limit)
            ->consume($this->resolveTokens($messages, $limit));
        if ($rateLimit->isAccepted()) {
            return;
        }

        $retryAfter = $rateLimit->getRetryAfter();
        $retryAfterSeconds = max(0, $retryAfter->getTimestamp() - time());

        throw new TooManyRequestsHttpException(
            $retryAfterSeconds,
            'Too many requests',
            headers: [
                'X-RateLimit-Limit' => (string) $rateLimit->getLimit(),
                'X-RateLimit-Remaining' => (string) $rateLimit->getRemainingTokens(),
                'X-RateLimit-Reset' => (string) $retryAfter->getTimestamp(),
            ],
        );
    }

    private function resolveTokens(int $messages, int $limit): int
    {
        return min(max($messages, self::MESSAGES_MIN), $limit);
    }

    private function createLimiter(string $key, int $limit): LimiterInterface
    {
        return new RateLimiterFactory(
            [
                'id' => self::LIMITER_ID,
                'policy' => self::LIMITER_POLICY,
                'limit' => $limit,
                'interval' => $this->interval,
            ],
            $this->storage,
        )->create($key);
    }

    private function resolveLimit(): int
    {
        if (null === $this->elevatedRole || StringHelper::isEmpty($this->elevatedRole)) {
            return $this->limit;
        }
        if (null === $this->elevatedLimit || $this->elevatedLimit < self::LIMIT_MIN) {
            return $this->limit;
        }
        if ($this->security->isGranted($this->elevatedRole)) {
            return $this->elevatedLimit;
        }

        return $this->limit;
    }
}
