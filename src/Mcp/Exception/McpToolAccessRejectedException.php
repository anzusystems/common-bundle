<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Exception;

final class McpToolAccessRejectedException extends McpToolCallRejectedException
{
    public static function createNotGranted(string $toolName): self
    {
        return new self(sprintf('Access denied — the current MCP user is not allowed to use the tool "%s".', $toolName));
    }

    /**
     * @param list<string> $unknownArguments
     * @param list<string> $allowedArguments
     */
    public static function createUnknownArguments(array $unknownArguments, array $allowedArguments): self
    {
        return new self(sprintf(
            'Unknown argument%s "%s". Allowed arguments: %s.',
            1 === count($unknownArguments) ? '' : 's',
            implode('", "', $unknownArguments),
            implode(', ', $allowedArguments),
        ));
    }

    public static function createInvalidArguments(string $message): self
    {
        return new self($message);
    }
}
