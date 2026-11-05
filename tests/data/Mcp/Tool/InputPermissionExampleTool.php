<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Model\Request\SearchAppLogsRequest;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use Closure;

#[AsMcpTool(
    name: InputPermissionExampleTool::NAME,
    description: 'Lists an example.',
    category: ExampleToolCategory::EXAMPLES,
    readOnly: true,
    permission: InputPermissionExampleTool::PERMISSION,
    inputPermission: InputPermissionExampleTool::INPUT_PERMISSION,
)]
final readonly class InputPermissionExampleTool
{
    public const string NAME = 'example_input_permission';
    public const string PERMISSION = 'example_read';
    public const string INPUT_PERMISSION = 'example_input';

    /**
     * @param Closure(SearchAppLogsRequest): McpLogsByContextResponse $handler
     */
    public function __construct(
        private Closure $handler,
    ) {
    }

    public function __invoke(SearchAppLogsRequest $request): McpLogsByContextResponse
    {
        return ($this->handler)($request);
    }
}
