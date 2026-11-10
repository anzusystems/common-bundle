<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Model\Request\SearchAppLogsRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response\ExampleWritePreviewResponse;
use Closure;

#[AsMcpTool(
    name: OtherInputExamplePreviewTool::NAME,
    description: 'Previews a written example from another input.',
    category: ExampleToolCategory::EXAMPLES,
    readOnly: true,
    previewOf: ExampleWriteTool::NAME,
)]
final readonly class OtherInputExamplePreviewTool
{
    public const string NAME = 'other_input_example_preview';

    /**
     * @param Closure(SearchAppLogsRequest): ExampleWritePreviewResponse $handler
     */
    public function __construct(
        private Closure $handler,
    ) {
    }

    public function __invoke(SearchAppLogsRequest $request): ExampleWritePreviewResponse
    {
        return ($this->handler)($request);
    }
}
