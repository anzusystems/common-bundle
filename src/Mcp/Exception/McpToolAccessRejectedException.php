<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Exception;

final class McpToolAccessRejectedException extends McpToolCallRejectedException
{
    public static function createNotGranted(string $toolName): self
    {
        return new self(sprintf('Access denied — the current MCP user is not allowed to use the tool "%s".', $toolName));
    }

    public static function createPromptNotGranted(string $promptName): self
    {
        return new self(sprintf('Access denied — the current MCP user is not allowed to use every tool of the prompt "%s".', $promptName));
    }
}
