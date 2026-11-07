<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security;

use AnzuSystems\CommonBundle\Helper\StringHelper;
use AnzuSystems\CommonBundle\Mcp\Exception\McpWriteRejectedException;
use AnzuSystems\CommonBundle\Mcp\McpWriteRateLimiter;
use AnzuSystems\CommonBundle\Mcp\Model\McpPreviewHashAwareInterface;
use AnzuSystems\CommonBundle\Mcp\Model\McpWritePreviewInterface;
use AnzuSystems\Contracts\AnzuApp;
use Mcp\Schema\Result\InputRequiredResult;
use Mcp\Server\RequestContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Throwable;

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

    public function assertAnswerConfirmed(string $toolName, McpPreviewHashAwareInterface $input, RequestContext $context): void
    {
        if ($this->writeConfirmation->isSupportedBy($context) && $this->writeConfirmation->isAnswered($context, $input)) {
            $this->writeConfirmation->assertConfirmed(toolName: $toolName, context: $context, input: $input);
        }
    }

    public function guardPreview(
        string $toolName,
        string $previewToolName,
        McpPreviewHashAwareInterface $input,
        RequestContext $context,
        McpWritePreviewInterface $preview,
    ): ?InputRequiredResult {
        if (null === $preview->previewHash) {
            return null;
        }
        if (StringHelper::isNotSame($preview->previewHash, $input->previewHash)) {
            throw McpWriteRejectedException::createPreviewStale($previewToolName);
        }
        if ($this->writeConfirmation->isSupportedBy($context) && false === $this->writeConfirmation->isAnswered($context, $input)) {
            return $this->writeConfirmation->createInputRequiredResult(input: $input, preview: $preview);
        }
        if (null === $preview->currentState) {
            $this->consumeReserved(toolName: $toolName, input: $input);

            return null;
        }
        $this->writeRateLimiter->consume($toolName);

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

    private function consumeReserved(string $toolName, McpPreviewHashAwareInterface $input): void
    {
        $this->previewHashReservation->reserve($toolName, $input);
        try {
            $this->writeRateLimiter->consume($toolName);
        } catch (Throwable $exception) {
            $this->previewHashReservation->release($input);

            throw $exception;
        }
    }
}
