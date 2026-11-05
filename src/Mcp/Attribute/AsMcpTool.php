<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Attribute;

use Attribute;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

/**
 * An MCP tool whose input schema comes from the input class of its __invoke() and whose description lists the fields of
 * the class it returns.
 * The description values replace the {{ name }} placeholders of the description, e.g. the NAME of another tool it points to.
 * A tool is a write tool unless it says readOnly, so a forgotten flag hides it from tokens without the write grant;
 * destructive and idempotent describe a write tool only, and every tool works on the data of this application alone.
 * The category groups the tool for a token form; a preview (previewOf) is read only, takes the permission of its write tool
 * and is granted exactly with it. Any other tool names the permission of the host it needs, a log tool of this bundle
 * takes it from mcp.log_tools_permission, because a bundle does not know the permissions of its host.
 * permission holds for the tool whatever its input, so tools/list can check it. inputPermission is checked on the input of
 * every call, with the input as the voter subject, and McpInputEnricherInterface then narrows that input to what the user may read.
 * rateLimiter names a Symfony rate limiter of a write tool: every write that passes its preview takes one token.
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
        public readonly ?string $inputPermission = null,
        public readonly ?string $rateLimiter = null,
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
