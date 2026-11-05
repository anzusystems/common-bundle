<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Exception;

final class McpDateWindowInputException extends McpToolInputException
{
    public static function createInvertedDateWindow(): self
    {
        return new self('publishedUntil must not be before publishedFrom.');
    }

    public static function createInvertedLogWindow(): self
    {
        return new self('until must not be before from.');
    }

    public static function createInvalidDate(string $paramName, string $value): self
    {
        return new self(sprintf(
            'Invalid %s value "%s", provide an ISO 8601 date-time, e.g. "2026-07-09T06:00:00+02:00".',
            $paramName,
            $value,
        ));
    }
}
