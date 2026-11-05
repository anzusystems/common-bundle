<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security;

use AnzuSystems\CommonBundle\Mcp\Exception\McpWriteRejectedException;
use AnzuSystems\Contracts\AnzuApp;
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

    public function markWritten(): void
    {
        $this->requestStack->getMainRequest()?->attributes
            ->set(self::WRITE_TOOL_DONE_ATTR, true);
    }
}
