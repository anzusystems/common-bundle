<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Mcp\Exception\McpWriteRejectedException;
use Psr\Container\ContainerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * The budget of writes per user, on top of the request limit of McpRateLimiter. Tools naming one limiter share it.
 */
final readonly class McpWriteRateLimiter
{
    /**
     * @param array<string, string> $toolRateLimiters tool name => rate limiter name
     */
    public function __construct(
        private ContainerInterface $rateLimiters,
        private array $toolRateLimiters,
        private CurrentAnzuUserProvider $currentUserProvider,
    ) {
    }

    public function consume(string $toolName): void
    {
        $rateLimiterName = $this->toolRateLimiters[$toolName] ?? null;
        if (null === $rateLimiterName) {
            return;
        }
        /** @var RateLimiterFactory $rateLimiter */
        $rateLimiter = $this->rateLimiters->get($rateLimiterName);
        $rateLimit = $rateLimiter
            ->create((string) $this->currentUserProvider->getCurrentUser()->getId())
            ->consume();
        if ($rateLimit->isAccepted()) {
            return;
        }

        throw McpWriteRejectedException::createRateLimitExceeded(
            array_keys($this->toolRateLimiters, $rateLimiterName, true),
            $rateLimit,
        );
    }
}
