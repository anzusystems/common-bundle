<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

final readonly class InvalidNameExampleTool
{
    public const string NAME = 'list examples';

    #[McpTool(name: self::NAME, annotations: new ToolAnnotations(readOnlyHint: true))]
    public function listExamples(): array
    {
        return [];
    }
}
