<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Attribute;

use Attribute;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

/**
 * An MCP tool whose input schema comes from the input class of its __invoke() and whose description lists the fields of
 * the class it returns.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AsMcpTool extends McpTool
{
    public const string CATEGORY_DIAGNOSTICS = 'diagnostics';
    public const string LOG_TOOLS_PERMISSION_PARAM = 'anzu_systems_common.mcp.log_tools_permission';
    public const string PERMISSION_LOG_TOOLS = '%' . self::LOG_TOOLS_PERMISSION_PARAM . '%';

    /**
     * @param array<string, int|float|string> $descriptionValues
     */
    public function __construct(
        string $name,
        string $description,
        public readonly string $category,
        bool $readOnly = false,
        bool $destructive = true,
        bool $idempotent = false,
        array $descriptionValues = [],
        public readonly ?string $previewOf = null,
        public readonly ?string $permission = null,
        public readonly ?string $rateLimiter = null,
        public readonly bool $explicitGrant = false,
    ) {
        parent::__construct(
            name: $name,
            description: McpField::fillPlaceholders($description, $descriptionValues),
            annotations: $readOnly
                ? new ToolAnnotations(readOnlyHint: true, openWorldHint: false)
                : new ToolAnnotations(readOnlyHint: false, destructiveHint: $destructive, idempotentHint: $idempotent, openWorldHint: false),
        );
    }
}
