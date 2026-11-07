<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Attribute;

use Attribute;
use Mcp\Capability\Attribute\McpPrompt;

/**
 * An MCP prompt that a request sees only when it may use every tool the prompt names.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AsMcpPrompt extends McpPrompt
{
    /**
     * @param list<string> $tools names of the tools the prompt leads the agent to call
     */
    public function __construct(
        string $name,
        string $description,
        public readonly array $tools = [],
        ?string $title = null,
    ) {
        parent::__construct(
            name: $name,
            title: $title,
            description: $description,
        );
    }
}
