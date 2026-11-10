<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request;

use AnzuSystems\CommonBundle\Mcp\Model\McpPreviewHashAwareInterface;
use AnzuSystems\CommonBundle\Mcp\Model\McpPreviewHashTrait;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleWritePreviewTool;

final class ExampleWriteRequest extends ExampleWritePreviewRequest implements McpPreviewHashAwareInterface
{
    use McpPreviewHashTrait;

    private const string PREVIEW_TOOL = ExampleWritePreviewTool::NAME;
}
