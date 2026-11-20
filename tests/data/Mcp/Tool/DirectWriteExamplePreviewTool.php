<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExampleWritePreviewRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response\ExampleWritePreviewResponse;
use Closure;

#[AsMcpTool(
    name: DirectWriteExamplePreviewTool::NAME,
    description: 'Previews an example lock.',
    category: ExampleToolCategory::EXAMPLES,
    readOnly: true,
    previewOf: DirectWriteExampleTool::NAME,
)]
final readonly class DirectWriteExamplePreviewTool
{
    public const string NAME = 'direct_write_example_preview_tool';

    /**
     * @param Closure(ExampleWritePreviewRequest): ExampleWritePreviewResponse $handler
     */
    public function __construct(
        private Closure $handler,
    ) {
    }

    public function __invoke(ExampleWritePreviewRequest $request): ExampleWritePreviewResponse
    {
        return ($this->handler)($request);
    }
}
