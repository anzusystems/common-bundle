<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use Closure;

#[AsMcpTool(
    name: ExplicitGrantExampleTool::NAME,
    description: 'Reads an example draft.',
    category: ExampleToolCategory::EXAMPLES,
    readOnly: true,
    permission: ExamplePermissionTool::PERMISSION,
    explicitGrant: true,
)]
final readonly class ExplicitGrantExampleTool
{
    public const string NAME = 'example_explicit_grant';

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
