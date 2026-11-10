<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExampleWritePreviewRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response\ExampleWritePreviewResponse;
use Closure;

#[AsMcpTool(
    name: ExtraFieldExamplePreviewTool::NAME,
    description: 'Previews an example whose write input has a field of its own.',
    category: ExampleToolCategory::EXAMPLES,
    readOnly: true,
    previewOf: ExtraFieldExampleTool::NAME,
)]
final readonly class ExtraFieldExamplePreviewTool
{
    public const string NAME = 'extra_field_example_preview';

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
