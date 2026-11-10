<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;

#[AsMcpTool(name: self::NAME, description: 'Returns an error result without structured content.', category: ExampleToolCategory::EXAMPLES)]
final readonly class ExampleErrorResultWithoutKeyTool
{
    public const string NAME = 'example_error_result_without_key';

    public function __invoke(): CallToolResult
    {
        return new CallToolResult([new TextContent('The example was not saved.')], isError: true);
    }
}
