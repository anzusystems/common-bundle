<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

final readonly class PlainMcpToolExampleTool
{
    /**
     * @return array<string, string>
     */
    #[McpTool(name: 'plain_example', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function __invoke(string $query): array
    {
        return ['query' => $query];
    }
}
