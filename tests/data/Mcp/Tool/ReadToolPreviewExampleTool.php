<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use Closure;

#[AsMcpTool(
    name: ReadToolPreviewExampleTool::NAME,
    description: 'Previews a read tool.',
    category: ExampleToolCategory::EXAMPLES,
    readOnly: true,
    previewOf: ExampleSearchTool::NAME,
)]
final readonly class ReadToolPreviewExampleTool
{
    public const string NAME = 'read_tool_preview_example';

    /**
     * @param Closure(): McpLogsByContextResponse $handler
     */
    public function __construct(
        private Closure $handler,
    ) {
    }

    public function __invoke(): McpLogsByContextResponse
    {
        return ($this->handler)();
    }
}
