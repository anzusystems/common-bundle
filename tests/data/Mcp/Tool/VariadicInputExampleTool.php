<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Model\Request\SearchAppLogsRequest;
use Mcp\Schema\Result\CallToolResult;

#[AsMcpTool(
    name: VariadicInputExampleTool::NAME,
    description: 'Searches examples of several requests.',
    category: ExampleToolCategory::EXAMPLES,
    readOnly: true,
)]
final readonly class VariadicInputExampleTool
{
    public const string NAME = 'variadic_input_example';

    public function __invoke(SearchAppLogsRequest ...$requests): CallToolResult
    {
        return new CallToolResult([]);
    }
}
