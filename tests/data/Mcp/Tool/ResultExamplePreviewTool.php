<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use Mcp\Schema\Result\CallToolResult;

#[AsMcpTool(
    name: ResultExamplePreviewTool::NAME,
    description: 'Previews a written example and builds its own result.',
    category: ExampleToolCategory::EXAMPLES,
    readOnly: true,
    previewOf: ExampleWriteTool::NAME,
)]
final readonly class ResultExamplePreviewTool
{
    public const string NAME = 'result_example_preview';

    public function __invoke(): CallToolResult
    {
        return new CallToolResult([]);
    }
}
