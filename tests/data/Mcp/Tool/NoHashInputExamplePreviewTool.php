<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExampleWritePreviewRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response\ExampleWritePreviewResponse;
use Closure;

#[AsMcpTool(
    name: NoHashInputExamplePreviewTool::NAME,
    description: 'Previews an example whose write input has no hash.',
    category: ExampleToolCategory::EXAMPLES,
    readOnly: true,
    previewOf: NoHashInputExampleTool::NAME,
)]
final readonly class NoHashInputExamplePreviewTool
{
    public const string NAME = 'no_hash_input_example_preview';

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
