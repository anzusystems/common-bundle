<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model;

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
        public bool $explicitGrant = false,
    ) {
    }
}
