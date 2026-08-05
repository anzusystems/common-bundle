<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Resolver;

use AnzuSystems\CommonBundle\Mcp\Exception\McpToolInputException;
use AnzuSystems\CommonBundle\Mcp\Model\McpDateWindow;
use AnzuSystems\Contracts\AnzuApp;
use DateTimeImmutable;
use Exception;

final readonly class McpDateWindowResolver
{
    public const int DATE_RANGE_MAX_DAYS = 31;
    public const int LOG_WINDOW_MAX_DAYS = self::DATE_RANGE_MAX_DAYS;

    private const int LOG_WINDOW_DEFAULT_DAYS = 1;
    private const string ERROR_INVERTED_DATE_WINDOW = 'publishedUntil must not be before publishedFrom.';
    private const string ERROR_INVERTED_LOG_WINDOW = 'until must not be before from.';

    public function resolveArticleWindow(?string $publishedFrom, ?string $publishedUntil): McpDateWindow
    {
        $from = $this->parseDateTime('publishedFrom', $publishedFrom);
        $until = $this->parseDateTime('publishedUntil', $publishedUntil);
        $clampedFrom = $this->clampFrom($from, $until);

        return new McpDateWindow($clampedFrom, $this->clampUntil($clampedFrom, $until));
    }

    public function resolveLogWindow(?string $from, ?string $until): McpDateWindow
    {
        $parsedFrom = $this->parseDateTime('from', $from);
        $resolvedUntil = $this->parseDateTime('until', $until) ?? AnzuApp::date();
        $resolvedFrom = $parsedFrom ?? $resolvedUntil->modify(sprintf('-%d day', self::LOG_WINDOW_DEFAULT_DAYS));
        if ($resolvedUntil < $resolvedFrom) {
            throw new McpToolInputException(self::ERROR_INVERTED_LOG_WINDOW);
        }

        return new McpDateWindow($this->clampLogFrom($resolvedFrom, $resolvedUntil), $resolvedUntil);
    }

    public function parseDateTime(string $paramName, ?string $value): ?DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            throw new McpToolInputException(
                sprintf('Invalid %s value "%s", provide an ISO 8601 date-time, e.g. "2026-07-09T06:00:00+02:00".', $paramName, $value),
            );
        }
    }

    public function ensureNotInverted(?DateTimeImmutable $from, ?DateTimeImmutable $until): void
    {
        if ($from instanceof DateTimeImmutable && $until instanceof DateTimeImmutable && $until < $from) {
            throw new McpToolInputException(self::ERROR_INVERTED_DATE_WINDOW);
        }
    }

    private function clampLogFrom(DateTimeImmutable $from, DateTimeImmutable $until): DateTimeImmutable
    {
        $minFrom = $until->modify(sprintf('-%d days', self::LOG_WINDOW_MAX_DAYS));
        if ($from < $minFrom) {
            return $minFrom;
        }

        return $from;
    }

    private function clampFrom(?DateTimeImmutable $from, ?DateTimeImmutable $until): DateTimeImmutable
    {
        if ($from instanceof DateTimeImmutable) {
            return $from;
        }

        return ($until ?? AnzuApp::date())->modify(sprintf('-%d days', self::DATE_RANGE_MAX_DAYS));
    }

    private function clampUntil(DateTimeImmutable $clampedFrom, ?DateTimeImmutable $until): DateTimeImmutable
    {
        $maxUntil = $clampedFrom->modify(sprintf('+%d days', self::DATE_RANGE_MAX_DAYS));
        if (null === $until || $until > $maxUntil) {
            return $maxUntil;
        }
        if ($until < $clampedFrom) {
            throw new McpToolInputException(self::ERROR_INVERTED_DATE_WINDOW);
        }

        return $until;
    }
}
