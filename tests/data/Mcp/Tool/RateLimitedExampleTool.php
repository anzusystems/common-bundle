<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExampleWriteRequest;
use Closure;

#[AsMcpTool(
    name: RateLimitedExampleTool::NAME,
    description: 'Writes an example.',
    category: ExampleToolCategory::EXAMPLES,
    permission: 'example_write',
    rateLimiter: self::RATE_LIMITER,
)]
final readonly class RateLimitedExampleTool
{
    public const string NAME = 'rate_limited_example_tool';
    public const string RATE_LIMITER = 'example_write';

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
