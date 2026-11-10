<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use Closure;

#[AsMcpTool(
    name: PermissionExamplePreviewTool::NAME,
    description: 'Lists an example.',
    category: ExampleToolCategory::EXAMPLES,
    readOnly: true,
    previewOf: ExampleWithoutInputTool::NAME,
    permission: PermissionExamplePreviewTool::PERMISSION,
)]
final readonly class PermissionExamplePreviewTool
{
    public const string NAME = 'permission_example_preview';
    public const string PERMISSION = 'example_permission';

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
