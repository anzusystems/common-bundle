<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Exception;

final class McpDeleteRejectedException extends McpToolCallRejectedException
{
    private const string REFUSAL_SEPARATOR = ' ';

    /**
     * @param list<string> $refusals
     */
    public static function createRefused(string $resourceName, int|string $id, array $refusals): self
    {
        return new self(sprintf(
            'The %s "%s" cannot be deleted through MCP: %s Tell the user and do not retry.',
            $resourceName,
            $id,
            implode(self::REFUSAL_SEPARATOR, $refusals),
        ));
    }
}
