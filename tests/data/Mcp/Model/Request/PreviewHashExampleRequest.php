<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request;

use AnzuSystems\CommonBundle\Mcp\Model\McpPreviewHashTrait;

final class PreviewHashExampleRequest
{
    use McpPreviewHashTrait;

    private const string PREVIEW_TOOL = 'example_preview';
}
