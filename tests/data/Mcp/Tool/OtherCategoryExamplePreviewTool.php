<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use Closure;

#[AsMcpTool(
    name: OtherCategoryExamplePreviewTool::NAME,
    description: 'Previews an example from another category.',
    category: AsMcpTool::CATEGORY_DIAGNOSTICS,
    readOnly: true,
    previewOf: ExampleWithoutInputTool::NAME,
)]
final readonly class OtherCategoryExamplePreviewTool
{
    public const string NAME = 'other_category_example_preview';

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
