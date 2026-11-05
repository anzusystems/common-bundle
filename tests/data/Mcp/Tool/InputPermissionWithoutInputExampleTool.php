<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use Closure;

#[AsMcpTool(
    name: InputPermissionWithoutInputExampleTool::NAME,
    description: 'Lists an example.',
    category: ExampleToolCategory::EXAMPLES,
    readOnly: true,
    permission: 'example_read',
    inputPermission: 'example_input',
)]
final readonly class InputPermissionWithoutInputExampleTool
{
    public const string NAME = 'example_input_permission_without_input';

    /**
     * @param Closure(): McpLogsByContextResponse $handler
     */
    public function __construct(
        private Closure $handler,
    ) {
    }

    public function __invoke(): McpLogsByContextResponse
    {
        return ($this->handler)();
    }
}
