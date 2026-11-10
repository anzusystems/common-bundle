<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExampleWritePreviewRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response\ExampleWritePreviewResponse;
use Closure;

#[AsMcpTool(
    name: RateLimitedExamplePreviewTool::NAME,
    description: 'Previews a written example.',
    category: ExampleToolCategory::EXAMPLES,
    readOnly: true,
    previewOf: RateLimitedExampleTool::NAME,
)]
final readonly class RateLimitedExamplePreviewTool
{
    public const string NAME = 'rate_limited_example_preview_tool';

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
