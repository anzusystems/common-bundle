<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Exception;

use Symfony\Component\RateLimiter\RateLimit;

final class McpWriteRejectedException extends McpToolCallRejectedException
{
    public const string RATE_LIMIT_EXCEEDED_START = 'The write limit of';

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

    public static function createPreviewHashUsed(string $toolName, int $lifetimeSeconds): self
    {
        return new self(sprintf('This previewHash of "%s" was already used, so nothing changed. The earlier call may have created the record: check that first. To create the same record again on purpose, retry after %d seconds.', $toolName, $lifetimeSeconds));
    }

    /**
     * @param list<string> $toolNames
     */
    public static function createRateLimitExceeded(array $toolNames, RateLimit $rateLimit): self
    {
        return new self(sprintf(
            self::RATE_LIMIT_EXCEEDED_START . ' %d calls shared by %s is reached, try again after %s — do not retry before.',
            $rateLimit->getLimit(),
            implode(', ', $toolNames),
            $rateLimit->getRetryAfter()
                ->format(DATE_ATOM),
        ));
    }

    public static function createPreviewStale(string $previewToolName): self
    {
        return new self(sprintf('The arguments or the saved data differ from what %1$s returned this previewHash for: call %1$s again, show the user the result and get their consent again before you retry with the new previewHash.', $previewToolName));
    }
}
