<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Handler\McpToolReferenceHandler;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\RequestContext;

#[AsMcpTool(name: self::NAME, description: 'Returns an error result built by the tool.')]
final readonly class ExampleErrorResultTool
{
    public const string NAME = 'example_error_result';
    public const string ERROR = 'The example was not saved.';

    public function __invoke(RequestContext $context): CallToolResult
    {
        return new CallToolResult(
            [new TextContent(self::ERROR)],
            isError: true,
            structuredContent: [McpToolReferenceHandler::ERROR_KEY => self::ERROR],
        );
    }
}
