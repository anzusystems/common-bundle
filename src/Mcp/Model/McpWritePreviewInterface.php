<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model;

interface McpWritePreviewInterface
{
    public ?string $previewHash { get; }
    public McpConfirmationMessage $confirmationMessage { get; }
}
