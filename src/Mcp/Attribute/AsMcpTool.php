<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Attribute;

use Attribute;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

/**
 * An MCP tool whose input schema comes from the input class of its __invoke() and whose description lists the fields of
 * the class it returns.
 * The values replace the {{ name }} placeholders of the description, e.g. the NAME of another tool it points to.
 * A tool is a write tool unless it says readOnly, so a forgotten flag hides it from tokens without the write grant;
 * destructive and idempotent describe a write tool only, and every tool works on the data of this application alone.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AsMcpTool extends McpTool
{
    /**
     * @param array<string, int|float|string> $values
     */
    public function __construct(
        string $name,
        string $description,
        bool $readOnly = false,
        bool $destructive = true,
        bool $idempotent = false,
        array $values = [],
    ) {
        parent::__construct(
            name: $name,
            description: McpField::fillPlaceholders($description, $values),
            annotations: $readOnly
                ? new ToolAnnotations(readOnlyHint: true, openWorldHint: false)
                : new ToolAnnotations(readOnlyHint: false, destructiveHint: $destructive, idempotentHint: $idempotent, openWorldHint: false),
        );
    }
}
