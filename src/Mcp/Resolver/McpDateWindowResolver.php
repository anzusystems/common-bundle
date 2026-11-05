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
    private const string UTC_TIMEZONE = 'UTC';

    public function resolveArticleWindow(?string $publishedFrom, ?string $publishedUntil): McpDateWindow
    {
        $from = $this->parseDateTime('publishedFrom', $publishedFrom);
        $until = $this->parseDateTime('publishedUntil', $publishedUntil);
        $now = AnzuApp::date();
        $clampedFrom = $this->clampFrom($from, $until, $now);
        $clampedUntil = $this->clampUntil($clampedFrom, $until);

        return new McpDateWindow(
            $clampedFrom,
            $clampedUntil,
            truncated: $this->isUntilTruncated($from, $until, $clampedUntil, $now),
        );
    }

    public function resolveLogWindow(?string $from, ?string $until): McpDateWindow
    {
        $parsedFrom = $this->parseDateTime('from', $from);
        $resolvedUntil = $this->parseDateTime('until', $until) ?? AnzuApp::date();
        $resolvedFrom = $parsedFrom ?? $resolvedUntil->modify(sprintf('-%d day', self::LOG_WINDOW_DEFAULT_DAYS));
        if ($resolvedUntil < $resolvedFrom) {
            throw McpDateWindowInputException::createInvertedLogWindow();
        }
        $clampedFrom = $this->clampLogFrom($resolvedFrom, $resolvedUntil);

        return new McpDateWindow(
            $clampedFrom,
            $resolvedUntil,
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

    public function ensureNotInverted(?DateTimeImmutable $from, ?DateTimeImmutable $until): void
    {
        if ($from instanceof DateTimeImmutable && $until instanceof DateTimeImmutable && $until < $from) {
            throw McpDateWindowInputException::createInvertedDateWindow();
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
        if ($until < $clampedFrom) {
            throw McpDateWindowInputException::createInvertedDateWindow();
        }

        return $until;
    }
}
