<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response\ExampleWritePreviewResponse;

#[AsMcpTool(
    name: WithoutInputWriteExamplePreviewTool::NAME,
    description: 'Previews a write tool without an input.',
    category: ExampleToolCategory::EXAMPLES,
    readOnly: true,
    previewOf: ExampleWithoutInputTool::NAME,
)]
final readonly class WithoutInputWriteExamplePreviewTool
{
    public const string NAME = 'without_input_write_example_preview';

    public function __invoke(): ExampleWritePreviewResponse
    {
        return new ExampleWritePreviewResponse(title: self::NAME);
    }
}
