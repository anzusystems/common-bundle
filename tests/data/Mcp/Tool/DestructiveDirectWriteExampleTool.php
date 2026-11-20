<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExampleWritePreviewRequest;
use Closure;

#[AsMcpTool(
    name: DestructiveDirectWriteExampleTool::NAME,
    description: 'Locks an example.',
    category: ExampleToolCategory::EXAMPLES,
    permission: ExampleWriteTool::PERMISSION,
    idempotent: true,
    rateLimiter: RateLimitedExampleTool::RATE_LIMITER,
    directWrite: true,
)]
final readonly class DestructiveDirectWriteExampleTool
{
    public const string NAME = 'destructive_direct_write_example_tool';

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
