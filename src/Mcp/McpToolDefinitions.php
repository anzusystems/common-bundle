<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp;

use AnzuSystems\CommonBundle\Mcp\Model\McpToolDefinition;
use LogicException;

final readonly class McpToolDefinitions
{
    /**
     * @param array<string, McpToolDefinition> $definitions keyed by the tool name
     */
    public function __construct(
        private array $definitions = [],
    ) {
    }

    public function find(string $toolName): ?McpToolDefinition
    {
        return $this->definitions[$toolName] ?? null;
    }

    /**
     * @return array<string, McpToolDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * @return list<string>
     */
    public function getToolNames(): array
    {
        return array_filter(
            $this->definitions,
            static fn (McpToolDefinition $definition): bool => is_string($definition->permission),
        ) |> array_keys(...);
    }

    /**
     * @return list<string>
     */
    public function getWriteToolNames(): array
    {
        return array_filter(
            $this->definitions,
            static fn (McpToolDefinition $definition): bool => is_string($definition->permission) && false === $definition->readOnly,
        ) |> array_keys(...);
    }

    public function getToolCategory(string $toolName): string
    {
        return $this->find($toolName)
            ->category
            ?? throw new LogicException(sprintf('The MCP tool "%s" has no category.', $toolName));
    }

    public function getPreviewToolName(string $writeToolName): ?string
    {
        return $this->find($writeToolName)?->previewTool;
    }
}
