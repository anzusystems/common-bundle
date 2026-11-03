<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Model\Request\SearchAppLogsRequest;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\RequestContext;

#[AsMcpTool(
    name: ExampleContextTool::NAME,
    description: 'Echoes the called tool.',
    readOnly: true,
)]
final readonly class ExampleContextTool
{
    public const string NAME = 'example_context';

    public function __invoke(SearchAppLogsRequest $request, RequestContext $context): CallToolResult
    {
        $callToolRequest = $context->getRequest();
        $toolName = $callToolRequest instanceof CallToolRequest ? $callToolRequest->name : null;

        return new CallToolResult(
            [new TextContent((string) $toolName)],
            structuredContent: ['tool' => $toolName, 'level' => $request->level],
        );
    }
}
