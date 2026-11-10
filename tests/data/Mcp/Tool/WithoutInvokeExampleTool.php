<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;

#[AsMcpTool(
    name: WithoutInvokeExampleTool::NAME,
    description: 'Has no __invoke().',
    category: ExampleToolCategory::EXAMPLES,
    readOnly: true,
)]
final readonly class WithoutInvokeExampleTool
{
    public const string NAME = 'without_invoke_example';
}
