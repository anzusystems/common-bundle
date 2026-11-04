<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExampleWritePreviewRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response\ExampleWritePreviewResponse;
use Closure;

#[AsMcpTool(
    name: ExampleWritePreviewTool::NAME,
    description: 'Previews a written example.',
    category: ExampleToolCategory::EXAMPLES,
    readOnly: true,
    previewOf: ExampleWriteTool::NAME,
)]
final readonly class ExampleWritePreviewTool
{
    public const string NAME = 'example_write_preview';

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
