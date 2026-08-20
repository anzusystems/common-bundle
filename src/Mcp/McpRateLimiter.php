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
    public const string TOKEN_ATTRIBUTE_KEY = 'mcp_rate_limit_key';
    public const string TOKEN_ATTRIBUTE_LIMIT = 'mcp_rate_limit';

    private const string CONFIG_LIMIT = 'limit';

    /**
     * @param array<string, mixed> $rateLimiterConfig
     */
    public function __construct(
        private array $rateLimiterConfig,
        private StorageInterface $storage,
        private CurrentAnzuUserProvider $currentUserProvider,
        private Security $security,
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

        $key = $this->resolveTokenAttribute(self::TOKEN_ATTRIBUTE_KEY);
        $limit = $this->createLimiter(
            is_string($key) && StringHelper::isNotEmpty($key) ? $key : (string) $userId,
            $this->resolveTokenAttribute(self::TOKEN_ATTRIBUTE_LIMIT),
        )->consume();
        if ($limit->isAccepted()) {
            return;
        }

        $retryAfter = $limit->getRetryAfter();
        $retryAfterSeconds = max(0, $retryAfter->getTimestamp() - time());

        throw new TooManyRequestsHttpException(
            $retryAfterSeconds,
            'Too many requests',
            headers: [
                'X-RateLimit-Limit' => (string) $limit->getLimit(),
                'X-RateLimit-Remaining' => (string) $limit->getRemainingTokens(),
                'X-RateLimit-Reset' => (string) $retryAfter->getTimestamp(),
            ],
        );
    }

    private function resolveTokenAttribute(string $name): mixed
    {
        return $this->security->getToken()?->getAttributes()[$name] ?? null;
    }

    private function createLimiter(string $key, mixed $limitOverride): LimiterInterface
    {
        $config = $this->rateLimiterConfig;
        if (is_int($limitOverride) && $limitOverride > 0) {
            $config[self::CONFIG_LIMIT] = $limitOverride;
        }

        return new RateLimiterFactory($config, $this->storage)
            ->create($key);
    }
}
