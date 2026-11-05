<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExampleWriteRequest;
use Closure;

#[AsMcpTool(
    name: ExampleWriteTool::NAME,
    description: 'Writes an example.',
    category: ExampleToolCategory::EXAMPLES,
    permission: 'example_write',
)]
final readonly class ExampleWriteTool
{
    public const string NAME = 'example_write';

    /**
     * @param Closure(ExampleWriteRequest): McpLogsByContextResponse $handler
     */
    public function __construct(
        private Closure $handler,
    ) {
    }

    public function __invoke(ExampleWriteRequest $request): McpLogsByContextResponse
    {
        return ($this->handler)($request);
    }
}
