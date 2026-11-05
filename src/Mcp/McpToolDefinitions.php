<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp;

use AnzuSystems\CommonBundle\Mcp\Model\McpToolDefinition;

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
}
