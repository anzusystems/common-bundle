<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security;

use AnzuSystems\CommonBundle\Helper\StringHelper;
use AnzuSystems\CommonBundle\Mcp\Exception\McpWriteRejectedException;
use AnzuSystems\CommonBundle\Mcp\McpWriteRateLimiter;
use AnzuSystems\CommonBundle\Mcp\Model\McpPreviewHashAwareInterface;
use AnzuSystems\CommonBundle\Mcp\Model\McpWritePreviewInterface;
use AnzuSystems\Contracts\AnzuApp;
use Closure;
use Mcp\Schema\Result\InputRequiredResult;
use Mcp\Server\RequestContext;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Keeps one HTTP request (one JSON-RPC batch) to at most one successful write: the main request is marked after a
 * write tool returned, so the guard does not depend on audit resources. Without a main request there is no guard.
 */
final readonly class McpWriteToolGuard
{
    private const string WRITE_TOOL_DONE_ATTR = '_mcp_write_tool_done';

    public function __construct(
        private McpToolAccessChecker $toolAccessChecker,
        private RequestStack $requestStack,
        private McpWriteConfirmation $writeConfirmation,
        private McpWriteRateLimiter $writeRateLimiter,
        private McpPreviewHashReservation $previewHashReservation,
    ) {
    }

    public function assertWriteGranted(string $toolName): void
    {
        if ($this->toolAccessChecker->isWriteGranted($toolName)) {
            return;
        }

        throw McpWriteRejectedException::createNotGranted($toolName);
    }

    public function assertWritable(string $toolName): void
    {
        if (AnzuApp::isReadOnlyMode()) {
            throw McpWriteRejectedException::createReadOnlyMode();
        }
        if (true === $this->requestStack->getMainRequest()?->attributes->has(self::WRITE_TOOL_DONE_ATTR)) {
            throw McpWriteRejectedException::createAlreadyWritten($toolName);
        }
    }

    /**
     * A write runs only with the previewHash its preview returns right now. A preview without a hash has nothing to write
     * (no change, or a reason the tool itself reports), so the tool runs and takes no token.
     *
     * @param Closure(): McpWritePreviewInterface $runPreview
     */
    public function guardPreview(
        string $toolName,
        string $previewToolName,
        McpPreviewHashAwareInterface $input,
        RequestContext $context,
        Closure $runPreview,
    ): ?InputRequiredResult {
        $isConfirmable = $this->writeConfirmation->isSupportedBy($context);
        $isAnswered = $isConfirmable && $this->writeConfirmation->isAnswered($context, $input);
        if ($isAnswered) {
            $this->writeConfirmation->assertConfirmed(toolName: $toolName, context: $context, input: $input);
        }
        $preview = $runPreview();
        if (null === $preview->previewHash) {
            return null;
        }
        if (StringHelper::isNotSame($preview->previewHash, $input->previewHash)) {
            throw McpWriteRejectedException::createPreviewStale($previewToolName);
        }
        if ($isConfirmable && false === $isAnswered) {
            return $this->writeConfirmation->createInputRequiredResult(input: $input, preview: $preview);
        }
        $this->writeRateLimiter->consume($toolName);
        if (null === $preview->currentState) {
            $this->previewHashReservation->reserve($toolName, $input);
        }

        return null;
    }

    public function releasePreviewHash(McpPreviewHashAwareInterface $input): void
    {
        $this->previewHashReservation->release($input);
    }

    public function markWritten(): void
    {
        $this->requestStack->getMainRequest()?->attributes
            ->set(self::WRITE_TOOL_DONE_ATTR, true);
    }
}
