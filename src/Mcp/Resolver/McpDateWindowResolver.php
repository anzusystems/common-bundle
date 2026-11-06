<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Resolver;

use AnzuSystems\CommonBundle\Mcp\Exception\McpDateWindowInputException;
use AnzuSystems\CommonBundle\Mcp\Model\McpDateWindow;
use AnzuSystems\Contracts\AnzuApp;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

final readonly class McpDateWindowResolver
{
    public const int DATE_RANGE_MAX_DAYS = 31;
    public const int LOG_WINDOW_MAX_DAYS = self::DATE_RANGE_MAX_DAYS;
    public const string DATE_TIME_OFFSET_DESCRIPTION = 'Any UTC offset is honoured; a value without an offset is read as UTC.';

    private const int LOG_WINDOW_DEFAULT_DAYS = 1;
    private const string LOG_FROM_NAME = 'from';
    private const string LOG_UNTIL_NAME = 'until';
    private const string UTC_TIMEZONE = 'UTC';

    public function resolveDateRange(string $fromName, ?string $from, string $untilName, ?string $until): McpDateWindow
    {
        $parsedFrom = $this->parseDateTime($fromName, $from);
        $parsedUntil = $this->parseDateTime($untilName, $until);
        $this->ensureNotInverted(fromName: $fromName, from: $parsedFrom, untilName: $untilName, until: $parsedUntil);
        $now = AnzuApp::date();
        $clampedFrom = $this->clampFrom(from: $parsedFrom, until: $parsedUntil, now: $now);
        $clampedUntil = $this->clampUntil($clampedFrom, $parsedUntil);

        return new McpDateWindow(
            from: $clampedFrom,
            until: $clampedUntil,
            truncated: $this->isUntilTruncated(from: $parsedFrom, until: $parsedUntil, clampedUntil: $clampedUntil, now: $now),
        );
    }

    public function resolveLogWindow(?string $from, ?string $until): McpDateWindow
    {
        $parsedFrom = $this->parseDateTime(self::LOG_FROM_NAME, $from);
        $resolvedUntil = $this->parseDateTime(self::LOG_UNTIL_NAME, $until) ?? AnzuApp::date();
        $resolvedFrom = $parsedFrom ?? $resolvedUntil->modify(sprintf('-%d day', self::LOG_WINDOW_DEFAULT_DAYS));
        $this->ensureNotInverted(fromName: self::LOG_FROM_NAME, from: $resolvedFrom, untilName: self::LOG_UNTIL_NAME, until: $resolvedUntil);
        $clampedFrom = $this->clampLogFrom($resolvedFrom, $resolvedUntil);

        return new McpDateWindow(
            from: $clampedFrom,
            until: $resolvedUntil,
            truncated: $this->isFromTruncated($resolvedFrom, $clampedFrom),
        );
    }

    public function parseDateTime(string $paramName, ?string $value): ?DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }

        try {
            $utc = new DateTimeZone(self::UTC_TIMEZONE);

            return new DateTimeImmutable($value, $utc)
                ->setTimezone($utc);
        } catch (Exception) {
            throw McpDateWindowInputException::createInvalidDate($paramName, $value);
        }
    }

    public function ensureNotInverted(string $fromName, ?DateTimeImmutable $from, string $untilName, ?DateTimeImmutable $until): void
    {
        if ($from instanceof DateTimeImmutable && $until instanceof DateTimeImmutable && $until < $from) {
            throw McpDateWindowInputException::createInverted($fromName, $untilName);
        }
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
