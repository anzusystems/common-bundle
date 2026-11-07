<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model;

use Mcp\Schema\Prompt;

final readonly class McpPromptDefinition
{
    /**
     * @param array{class-string, string} $handler
     */
    public function __construct(
        public Prompt $prompt,
        public array $handler,
    ) {
    }
}
