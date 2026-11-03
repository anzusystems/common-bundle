<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;

#[AsMcpTool(
    name: InvalidReturnExampleTool::NAME,
    description: 'Lists examples as an array.',
    readOnly: true,
)]
final readonly class InvalidReturnExampleTool
{
    public const string NAME = 'invalid_return_example';

    public function __invoke(): array
    {
        return [];
    }
}
