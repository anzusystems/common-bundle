<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;

#[AsMcpTool(
    name: StandaloneExampleTool::NAME,
    description: 'Lists examples without the base tool.',
    readOnly: true,
)]
final readonly class StandaloneExampleTool
{
    public const string NAME = 'standalone_example';

    public function __invoke(): array
    {
        return [];
    }
}
