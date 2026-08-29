<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Resolver;

use AnzuSystems\CommonBundle\Mcp\Resolver\McpPageWindowResolver;
use PHPUnit\Framework\TestCase;

final class McpPageWindowResolverTest extends TestCase
{
    private const int LIMIT_MAX = 50;

    private McpPageWindowResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new McpPageWindowResolver();
    }

    public function testWindowWithinCapsIsNotClamped(): void
    {
        $window = $this->resolver->resolve(3, 20, self::LIMIT_MAX);

        self::assertSame(3, $window->page);
        self::assertSame(20, $window->limit);
        self::assertSame(40, $window->getOffset());
        self::assertFalse($window->isClamped());
    }

    public function testPageAndLimitAboveMaxAreClampedAndReported(): void
    {
        $window = $this->resolver->resolve(999, 999, self::LIMIT_MAX);

        self::assertSame(McpPageWindowResolver::PAGE_MAX, $window->page);
        self::assertSame(self::LIMIT_MAX, $window->limit);
        self::assertTrue($window->isClamped());
        self::assertSame(McpPageWindowResolver::PAGE_MAX * self::LIMIT_MAX, $window->getReachableRows());
        self::assertStringContainsString('999', $this->resolver->getClampWarning($window));
    }

    public function testPageBelowMinIsRaisedToFirstPageAndSaysSo(): void
    {
        $window = $this->resolver->resolve(0, 0, self::LIMIT_MAX);

        self::assertSame(1, $window->page);
        self::assertSame(1, $window->limit);
        self::assertTrue($window->isClamped());
        self::assertStringContainsString('below the minimum', $this->resolver->getClampWarning($window));
    }
}
