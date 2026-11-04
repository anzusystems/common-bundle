<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExampleWritePreviewRequest;
use Closure;

#[AsMcpTool(
    name: NoHashInputExampleTool::NAME,
    description: 'Writes an example from an input without a hash.',
    category: ExampleToolCategory::EXAMPLES,
)]
final readonly class NoHashInputExampleTool
{
    public const string NAME = 'no_hash_input_example';

    /**
     * @param Closure(ExampleWritePreviewRequest): McpLogsByContextResponse $handler
     */
    public function __construct(
        private Closure $handler,
    ) {
    }

    public function __invoke(ExampleWritePreviewRequest $request): McpLogsByContextResponse
    {
        return ($this->handler)($request);
    }
}
