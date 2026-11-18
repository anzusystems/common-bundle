<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExampleWriteRequest;
use Closure;

#[AsMcpTool(
    name: HashInputDirectWriteExampleTool::NAME,
    description: 'Locks an example.',
    category: ExampleToolCategory::EXAMPLES,
    permission: ExampleWriteTool::PERMISSION,
    destructive: false,
    idempotent: true,
    rateLimiter: RateLimitedExampleTool::RATE_LIMITER,
    directWrite: true,
)]
final readonly class HashInputDirectWriteExampleTool
{
    public const string NAME = 'hash_input_direct_write_example_tool';

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
