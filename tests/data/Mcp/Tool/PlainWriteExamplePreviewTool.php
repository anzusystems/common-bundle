<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response\ExampleWritePreviewResponse;

#[AsMcpTool(
    name: PlainWriteExamplePreviewTool::NAME,
    description: 'Previews a write tool declared by a plain McpTool.',
    category: ExampleToolCategory::EXAMPLES,
    readOnly: true,
    previewOf: PlainWriteExampleTool::NAME,
)]
final readonly class PlainWriteExamplePreviewTool
{
    public const string NAME = 'plain_write_example_preview';

    public function __invoke(): ExampleWritePreviewResponse
    {
        return new ExampleWritePreviewResponse(title: self::NAME);
    }
}
