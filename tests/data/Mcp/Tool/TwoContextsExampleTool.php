<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\RequestContext;

#[AsMcpTool(
    name: TwoContextsExampleTool::NAME,
    description: 'Lists examples with two contexts.',
    category: ExampleToolCategory::EXAMPLES,
    readOnly: true,
)]
final readonly class TwoContextsExampleTool
{
    public const string NAME = 'two_contexts_example';

    public function __invoke(RequestContext $context, RequestContext $otherContext): CallToolResult
    {
        return new CallToolResult([]);
    }
}
