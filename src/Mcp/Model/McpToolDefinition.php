<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model;

/**
 * What the MCP handler and checkers know about one tool, built by McpToolDefinitionsCompilerPass from its attribute.
 */
final readonly class McpToolDefinition
{
    public function __construct(
        public ?string $permission = null,
        public bool $readOnly = false,
        public ?string $category = null,
        public ?string $previewOf = null,
        public ?string $previewTool = null,
        public ?string $rateLimiter = null,
        public ?McpToolSignature $signature = null,
    ) {
    }
}
