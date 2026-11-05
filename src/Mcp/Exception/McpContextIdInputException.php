<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Exception;

final class McpContextIdInputException extends McpToolInputException
{
    public static function createInvalid(string $contextId): self
    {
        return new self(sprintf('Invalid contextId "%s", provide a UUID taken from a log record.', $contextId));
    }
}
