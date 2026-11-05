<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Exception;

final class McpDateWindowInputException extends McpToolInputException
{
    public static function createInverted(string $fromName, string $untilName): self
    {
        return new self(sprintf('%s must not be before %s.', $untilName, $fromName));
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
