<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model;

interface McpWritePreviewInterface
{
    public ?string $previewHash { get; set; }
    public bool $writable { get; }
    public ?object $currentState { get; }
    public McpConfirmationMessage $confirmationMessage { get; }
}
