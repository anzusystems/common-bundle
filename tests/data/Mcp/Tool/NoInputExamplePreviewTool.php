<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response\ExampleWritePreviewResponse;

#[AsMcpTool(
    name: NoInputExamplePreviewTool::NAME,
    description: 'Previews a written example without an input.',
    category: ExampleToolCategory::EXAMPLES,
    readOnly: true,
    previewOf: ExampleWriteTool::NAME,
)]
final readonly class NoInputExamplePreviewTool
{
    public const string NAME = 'no_input_example_preview';

    public function __invoke(): ExampleWritePreviewResponse
    {
        return new ExampleWritePreviewResponse(title: self::NAME);
    }
}
