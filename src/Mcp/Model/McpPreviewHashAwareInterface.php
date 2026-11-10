<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model;

interface McpPreviewHashAwareInterface
{
    public string $previewHash { get; }
}
