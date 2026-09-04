<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Resolver;

use AnzuSystems\CommonBundle\Mcp\Exception\McpToolInputException;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpDateWindowResolver;
use PHPUnit\Framework\TestCase;

final class McpDateWindowResolverTest extends TestCase
{
    private McpDateWindowResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new McpDateWindowResolver();
    }

    public function testLogWindowDefaultsToOneDayBeforeUntil(): void
    {
        $window = $this->resolver->resolveLogWindow(null, '2026-07-20T12:00:00+00:00');

        self::assertSame('2026-07-19T12:00:00+00:00', $window->from->format(DATE_ATOM));
        self::assertSame('2026-07-20T12:00:00+00:00', $window->until->format(DATE_ATOM));
        self::assertFalse($window->truncated);
    }

    public function testLogWindowFromIsClampedToMaxDays(): void
    {
        $window = $this->resolver->resolveLogWindow('2026-01-01T00:00:00+00:00', '2026-07-20T00:00:00+00:00');

        self::assertSame('2026-06-19T00:00:00+00:00', $window->from->format(DATE_ATOM));
        self::assertTrue($window->truncated);
    }

    public function testLogWindowInvertedThrows(): void
    {
        $this->expectException(McpToolInputException::class);

        $this->resolver->resolveLogWindow('2026-07-20T00:00:00+00:00', '2026-07-19T00:00:00+00:00');
    }

    public function testLogWindowInvalidDateThrows(): void
    {
        $this->expectException(McpToolInputException::class);

        $this->resolver->resolveLogWindow('not-a-date', null);
    }

    public function testArticleWindowKeepsExplicitRangeWithinCap(): void
    {
        $window = $this->resolver->resolveArticleWindow('2026-07-01T00:00:00+00:00', '2026-07-10T00:00:00+00:00');

        self::assertSame('2026-07-01T00:00:00+00:00', $window->from->format(DATE_ATOM));
        self::assertSame('2026-07-10T00:00:00+00:00', $window->until->format(DATE_ATOM));
        self::assertFalse($window->truncated);
    }

    public function testArticleWindowUntilIsCappedToMaxDaysAfterFrom(): void
    {
        $window = $this->resolver->resolveArticleWindow('2026-01-01T00:00:00+00:00', '2026-06-01T00:00:00+00:00');

        self::assertSame('2026-02-01T00:00:00+00:00', $window->until->format(DATE_ATOM));
        self::assertTrue($window->truncated);
    }

    public function testArticleWindowWithoutDatesIsNotTruncated(): void
    {
        $window = $this->resolver->resolveArticleWindow(null, null);
        $length = $window->from->diff($window->until);

        self::assertFalse($window->truncated);
        self::assertSame(McpDateWindowResolver::DATE_RANGE_MAX_DAYS, (int) $length->days);
    }

    public function testOpenEndedArticleWindowOlderThanCapIsTruncated(): void
    {
        $window = $this->resolver->resolveArticleWindow('2020-01-01T00:00:00+00:00', null);

        self::assertSame('2020-02-01T00:00:00+00:00', $window->until->format(DATE_ATOM));
        self::assertTrue($window->truncated);
    }

    public function testArticleWindowInvertedThrows(): void
    {
        $this->expectException(McpToolInputException::class);

        $this->resolver->resolveArticleWindow('2026-07-10T00:00:00+00:00', '2026-07-01T00:00:00+00:00');
    }

    public function testEnsureNotInvertedThrows(): void
    {
        $this->expectException(McpToolInputException::class);

        $this->resolver->ensureNotInverted(
            $this->resolver->parseDateTime('from', '2026-07-10T00:00:00+00:00'),
            $this->resolver->parseDateTime('until', '2026-07-01T00:00:00+00:00'),
        );
    }
}
