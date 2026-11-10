<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use Mcp\Schema\Result\CallToolResult;

#[AsMcpTool(
    name: NullableReturnExampleTool::NAME,
    description: 'Lists examples or nothing.',
    category: ExampleToolCategory::EXAMPLES,
    readOnly: true,
)]
final readonly class NullableReturnExampleTool
{
    public const string NAME = 'nullable_return_example';

    public function __construct(
        private ?CallToolResult $result = null,
    ) {
    }

    public function __invoke(): ?CallToolResult
    {
        return $this->result;
    }
}
