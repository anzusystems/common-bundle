<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExampleWriteRequest;
use Closure;
use Mcp\Schema\Result\CallToolResult;

#[AsMcpTool(
    name: ExampleResultWriteTool::NAME,
    description: 'Writes an example and builds its own result.',
    category: ExampleToolCategory::EXAMPLES,
    permission: ExampleWriteTool::PERMISSION,
)]
final readonly class ExampleResultWriteTool
{
    public const string NAME = 'example_result_write';

    /**
     * @param Closure(): CallToolResult $handler
     */
    public function __construct(
        private Closure $handler,
    ) {
    }

    public function __invoke(ExampleWriteRequest $request): CallToolResult
    {
        return ($this->handler)();
    }
}
