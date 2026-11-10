<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use Closure;

#[AsMcpTool(
    name: WritablePreviewExampleTool::NAME,
    description: 'Previews an example without being read only.',
    category: ExampleToolCategory::EXAMPLES,
    previewOf: ExampleWithoutInputTool::NAME,
)]
final readonly class WritablePreviewExampleTool
{
    public const string NAME = 'writable_preview_example';

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
