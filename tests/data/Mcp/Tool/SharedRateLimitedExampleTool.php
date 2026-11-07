<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExampleWriteRequest;
use Closure;

#[AsMcpTool(
    name: SharedRateLimitedExampleTool::NAME,
    description: 'Writes an example.',
    category: ExampleToolCategory::EXAMPLES,
    permission: ExampleWriteTool::PERMISSION,
    rateLimiter: RateLimitedExampleTool::RATE_LIMITER,
)]
final readonly class SharedRateLimitedExampleTool
{
    public const string NAME = 'shared_rate_limited_example_tool';

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
