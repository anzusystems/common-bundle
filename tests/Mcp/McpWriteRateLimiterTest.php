<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Mcp\Exception\McpWriteRejectedException;
use AnzuSystems\CommonBundle\Mcp\McpWriteRateLimiter;
use AnzuSystems\Contracts\Entity\AnzuUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class McpWriteRateLimiterTest extends TestCase
{
    private const string LIMITER = 'example_write';
    private const string FIRST_TOOL = 'create_example';
    private const string SECOND_TOOL = 'delete_example';
    private const string OTHER_TOOL = 'update_other';

    public function testToolsNamingOneLimiterShareItAndTheErrorNamesThem(): void
    {
        $limiter = $this->createLimiter();

        $limiter->consume(self::FIRST_TOOL);

        $this->expectException(McpWriteRejectedException::class);
        $this->expectExceptionMessage(sprintf('shared by %s, %s is reached', self::FIRST_TOOL, self::SECOND_TOOL));

        $limiter->consume(self::SECOND_TOOL);
    }

    public function testToolWithoutALimiterTakesNoToken(): void
    {
        $limiter = $this->createLimiter();

        $limiter->consume(self::OTHER_TOOL);
        $limiter->consume(self::OTHER_TOOL);
        $limiter->consume(self::FIRST_TOOL);

        $this->addToAssertionCount(1);
    }

    private function createLimiter(): McpWriteRateLimiter
    {
        $user = $this->createConfiguredMock(AnzuUser::class, ['getId' => 5]);
        $currentUserProvider = $this->createMock(CurrentAnzuUserProvider::class);
        $currentUserProvider->method('getCurrentUser')
            ->willReturn($user);
        $factory = new RateLimiterFactory(
            ['id' => self::LIMITER, 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 hour'],
            new InMemoryStorage(),
        );

        return new McpWriteRateLimiter(
            rateLimiters: new ServiceLocator([self::LIMITER => static fn (): RateLimiterFactory => $factory]),
            toolRateLimiters: [self::FIRST_TOOL => self::LIMITER, self::SECOND_TOOL => self::LIMITER],
            currentUserProvider: $currentUserProvider,
        );
    }
}
