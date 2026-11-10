<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExtraFieldExampleWriteRequest;
use Closure;

#[AsMcpTool(
    name: ExtraFieldExampleTool::NAME,
    description: 'Writes an example from an input with a field its preview does not have.',
    category: ExampleToolCategory::EXAMPLES,
    permission: ExampleWriteTool::PERMISSION,
)]
final readonly class ExtraFieldExampleTool
{
    public const string NAME = 'extra_field_example';

    /**
     * @param Closure(ExtraFieldExampleWriteRequest): McpLogsByContextResponse $handler
     */
    public function __construct(
        private Closure $handler,
    ) {
    }

    public function __invoke(ExtraFieldExampleWriteRequest $request): McpLogsByContextResponse
    {
        return ($this->handler)($request);
    }
}
