<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Mcp\Exception\McpWriteRejectedException;
use AnzuSystems\CommonBundle\Mcp\Model\McpToolDefinition;
use Psr\Container\ContainerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final readonly class McpWriteRateLimiter
{
    public function __construct(
        private ContainerInterface $rateLimiters,
        private McpToolDefinitions $toolDefinitions,
        private CurrentAnzuUserProvider $currentUserProvider,
    ) {
    }

    public function consume(string $toolName): void
    {
        $rateLimiterName = $this->toolDefinitions->find($toolName)?->rateLimiter;
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
            array_filter(
                $this->toolDefinitions->all(),
                static fn (McpToolDefinition $definition): bool => $rateLimiterName === $definition->rateLimiter,
            ) |> array_keys(...),
            $rateLimit,
        );
    }
}
