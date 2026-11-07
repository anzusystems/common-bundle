<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use Mcp\Schema\Result\CallToolResult;

#[AsMcpTool(
    name: ScalarInputExampleTool::NAME,
    description: 'Lists examples up to a limit.',
    category: ExampleToolCategory::EXAMPLES,
    readOnly: true,
)]
final readonly class ScalarInputExampleTool
{
    public const string NAME = 'scalar_input_example';

    public function __invoke(int $limit): CallToolResult
    {
        return new CallToolResult([]);
    }
}
