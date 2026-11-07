<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Resolver;

use AnzuSystems\CommonBundle\Mcp\Model\McpDateWindow;
use AnzuSystems\Contracts\AnzuApp;
use DateTimeImmutable;

final readonly class McpDateWindowResolver
{
    public const int DATE_RANGE_MAX_DAYS = 31;
    public const int LOG_WINDOW_MAX_DAYS = self::DATE_RANGE_MAX_DAYS;
    public const string NOT_BEFORE_FROM_MESSAGE = 'This value must not be before {{ compared_value_path }}.';
    public const string NOT_IN_FUTURE_MESSAGE = 'This value must not be in the future.';

    private const int LOG_WINDOW_DEFAULT_DAYS = 1;

    public function resolveDateRange(?DateTimeImmutable $from, ?DateTimeImmutable $until): McpDateWindow
    {
        $now = AnzuApp::date();
        $clampedFrom = $this->clampFrom(from: $from, until: $until, now: $now);
        $clampedUntil = $this->clampUntil($clampedFrom, $until);

        return new McpDateWindow(
            from: $clampedFrom,
            until: $clampedUntil,
            truncated: $this->isUntilTruncated(from: $from, until: $until, clampedUntil: $clampedUntil, now: $now),
        );
    }

    public function resolveLogWindow(?DateTimeImmutable $from, ?DateTimeImmutable $until): McpDateWindow
    {
        $resolvedUntil = $until ?? AnzuApp::date();
        $resolvedFrom = $from ?? $resolvedUntil->modify(sprintf('-%d day', self::LOG_WINDOW_DEFAULT_DAYS));
        $clampedFrom = $this->clampLogFrom($resolvedFrom, $resolvedUntil);

        return new McpDateWindow(
            from: $clampedFrom,
            until: $resolvedUntil,
            truncated: $this->isFromTruncated($resolvedFrom, $clampedFrom),
        );
    }

    private function isFromTruncated(DateTimeImmutable $from, DateTimeImmutable $clampedFrom): bool
    {
        return $clampedFrom > $from;
    }

    private function isUntilTruncated(
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $until,
        DateTimeImmutable $clampedUntil,
        DateTimeImmutable $now,
    ): bool {
        if ($until instanceof DateTimeImmutable) {
            return $clampedUntil < $until;
        }
        if ($from instanceof DateTimeImmutable) {
            return $clampedUntil < $now;
        }

        return false;
    }

    private function clampLogFrom(DateTimeImmutable $from, DateTimeImmutable $until): DateTimeImmutable
    {
        $minFrom = $until->modify(sprintf('-%d days', self::LOG_WINDOW_MAX_DAYS));
        if ($from < $minFrom) {
            return $minFrom;
        }

        return $from;
    }

    private function clampFrom(?DateTimeImmutable $from, ?DateTimeImmutable $until, DateTimeImmutable $now): DateTimeImmutable
    {
        if ($from instanceof DateTimeImmutable) {
            return $from;
        }

        return ($until ?? $now)
            ->modify(sprintf('-%d days', self::DATE_RANGE_MAX_DAYS));
    }

    private function clampUntil(DateTimeImmutable $clampedFrom, ?DateTimeImmutable $until): DateTimeImmutable
    {
        $maxUntil = $clampedFrom->modify(sprintf('+%d days', self::DATE_RANGE_MAX_DAYS));
        if (null === $until || $until > $maxUntil) {
            return $maxUntil;
        }

        return $until;
    }
}
