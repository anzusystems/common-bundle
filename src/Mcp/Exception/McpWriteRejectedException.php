<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Exception;

final class McpWriteRejectedException extends McpToolCallRejectedException
{
    public static function createNotGranted(string $toolName): self
    {
        return new self(sprintf('Access denied — the write tool "%s" is not granted to this token. Create a new personal access token with this write tool.', $toolName));
    }

    public static function createReadOnlyMode(): self
    {
        return new self('The system is in read-only mode, changes are temporarily disabled — retry later.');
    }

    public static function createAlreadyWritten(string $toolName): self
    {
        return new self(sprintf('A write tool already ran in this request — send the call of "%s" in a separate request.', $toolName));
    }

    public static function createConfirmationRefused(string $toolName): self
    {
        return new self(sprintf('The confirmation of "%s" was refused or could not be shown to the user, so nothing changed. Tell the user and do not call it again unless they ask for it.', $toolName));
    }

    public static function createPreviewStale(string $previewToolName): self
    {
        return new self(sprintf('The arguments or the saved data differ from what %1$s returned this previewHash for: call %1$s again, show the user the result and pass the new previewHash.', $previewToolName));
    }
}
