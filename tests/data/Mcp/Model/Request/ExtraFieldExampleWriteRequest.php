<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request;

use AnzuSystems\CommonBundle\Mcp\Model\McpPreviewHashAwareInterface;
use AnzuSystems\CommonBundle\Mcp\Model\McpPreviewHashTrait;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExtraFieldExamplePreviewTool;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class ExtraFieldExampleWriteRequest extends ExampleWritePreviewRequest implements McpPreviewHashAwareInterface
{
    use McpPreviewHashTrait;

    public const string EXTRA_FIELD = 'reason';

    private const string PREVIEW_TOOL = ExtraFieldExamplePreviewTool::NAME;

    #[Serialize]
    public ?string $reason = null;
}
