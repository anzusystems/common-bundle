<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Resolver;

use AnzuSystems\CommonBundle\Mcp\Model\McpDateWindow;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpDateWindowResolver;
use AnzuSystems\Contracts\AnzuApp;
use DateTimeImmutable;
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
        $window = $this->resolver->resolveLogWindow(null, new DateTimeImmutable('2026-07-20T12:00:00+00:00'));

        self::assertSame('2026-07-19T12:00:00+00:00', $window->from->format(DATE_ATOM));
        self::assertSame('2026-07-20T12:00:00+00:00', $window->until->format(DATE_ATOM));
        self::assertFalse($window->truncated);
    }

    public function testLogWindowWithAFutureUntilDefaultsToOneDayBeforeNow(): void
    {
        $until = AnzuApp::date()->modify('+1 year');

        $window = $this->resolver->resolveLogWindow(null, $until);

        self::assertEquals(AnzuApp::date()->modify('-1 day'), $window->from);
        self::assertEquals($until, $window->until);
        self::assertFalse($window->truncated);
    }

    public function testLogWindowFromIsClampedToMaxDays(): void
    {
        $window = $this->resolver->resolveLogWindow(new DateTimeImmutable('2026-01-01T00:00:00+00:00'), new DateTimeImmutable('2026-07-20T00:00:00+00:00'));

        self::assertSame('2026-06-19T00:00:00+00:00', $window->from->format(DATE_ATOM));
        self::assertTrue($window->truncated);
    }

    public function testDateRangeKeepsExplicitRangeWithinCap(): void
    {
        $window = $this->resolveDateRange('2026-07-01T00:00:00+00:00', '2026-07-10T00:00:00+00:00');

        self::assertSame('2026-07-01T00:00:00+00:00', $window->from->format(DATE_ATOM));
        self::assertSame('2026-07-10T00:00:00+00:00', $window->until->format(DATE_ATOM));
        self::assertFalse($window->truncated);
    }

    public function testDateRangeUntilIsCappedToMaxDaysAfterFrom(): void
    {
        $window = $this->resolveDateRange('2026-01-01T00:00:00+00:00', '2026-06-01T00:00:00+00:00');

        self::assertSame('2026-02-01T00:00:00+00:00', $window->until->format(DATE_ATOM));
        self::assertTrue($window->truncated);
    }

    public function testDateRangeWithoutDatesIsNotTruncated(): void
    {
        $window = $this->resolveDateRange(null, null);
        $length = $window->from->diff($window->until);

        self::assertFalse($window->truncated);
        self::assertSame(McpDateWindowResolver::DATE_RANGE_MAX_DAYS, (int) $length->days);
    }

    public function testOpenEndedDateRangeOlderThanCapIsTruncated(): void
    {
        $window = $this->resolveDateRange('2020-01-01T00:00:00+00:00', null);

        self::assertSame('2020-02-01T00:00:00+00:00', $window->until->format(DATE_ATOM));
        self::assertTrue($window->truncated);
    }

    private function resolveDateRange(?string $from, ?string $until): McpDateWindow
    {
        return $this->resolver->resolveDateRange(
            is_string($from) ? new DateTimeImmutable($from) : null,
            is_string($until) ? new DateTimeImmutable($until) : null,
        );
    }
}
