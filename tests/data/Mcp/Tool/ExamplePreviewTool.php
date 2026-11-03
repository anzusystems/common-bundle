<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use Closure;

#[AsMcpTool(
    name: ExamplePreviewTool::NAME,
    description: 'Previews an example.',
    category: ExampleToolCategory::EXAMPLES,
    readOnly: true,
    previewOf: ExampleWithoutInputTool::NAME,
)]
final readonly class ExamplePreviewTool
{
    public const string NAME = 'example_preview';

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
