<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model;

/**
 * McpToolReferenceHandler signs the preview: it sets previewHash when writable is true, from the arguments and from
 * currentState, the decorator of what the write would change (null when it creates something new).
 */
interface McpWritePreviewInterface
{
    public ?string $previewHash { get; set; }
    public bool $writable { get; }
    public ?object $currentState { get; }
    public McpConfirmationMessage $confirmationMessage { get; }
}
