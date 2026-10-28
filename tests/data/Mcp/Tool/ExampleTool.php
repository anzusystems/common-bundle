<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

final readonly class ExampleTool
{
    #[McpTool(annotations: new ToolAnnotations(readOnlyHint: true))]
    public function listExamples(): array
    {
        return [];
    }

    #[McpTool]
    public function createExample(): array
    {
        return [];
    }
}
