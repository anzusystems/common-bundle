<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security;

use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Mcp\Exception\McpToolCallRejectedException;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Entity\Interfaces\BaseIdentifiableInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Keeps one HTTP request (one JSON-RPC batch) to at most one successful write: the main request is marked after a
 * write tool returned, so the guard does not depend on audit resources. Without a main request there is no guard.
 */
final readonly class McpWriteToolGuard
{
    public const string WRITE_TOOL_NOT_GRANTED_MESSAGE = 'Access denied — the write tool "%s" is not granted to this token. Create a new personal access token with this write tool.';
    public const string READ_ONLY_MODE_MESSAGE = 'The system is in read-only mode, changes are temporarily disabled — retry later.';
    public const string WRITE_TOOL_DONE_MESSAGE = 'A write tool already ran in this request — send the call of "%s" in a separate request.';

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

        throw new McpToolCallRejectedException(sprintf(self::WRITE_TOOL_NOT_GRANTED_MESSAGE, $toolName));
    }

    public function assertWritable(string $toolName): void
    {
        if (AnzuApp::isReadOnlyMode()) {
            throw new McpToolCallRejectedException(self::READ_ONLY_MODE_MESSAGE);
        }
        if (true === $this->requestStack->getMainRequest()?->attributes->has(self::WRITE_TOOL_DONE_ATTR)) {
            throw new McpToolCallRejectedException(sprintf(self::WRITE_TOOL_DONE_MESSAGE, $toolName));
        }
    }

    public function includeInAuditLogs(): void
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request) {
            return;
        }
        AuditLogResourceHelper::includeInAuditLogs($request);
    }

    public function markWritten(): void
    {
        $this->requestStack->getMainRequest()?->attributes
            ->set(self::WRITE_TOOL_DONE_ATTR, true);
    }

    public function excludeFailedWriteFromAuditLogs(): void
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request || AuditLogResourceHelper::hasResourceId($request)) {
            return;
        }
        AuditLogResourceHelper::excludeFromAuditLogs($request);
    }

    public function addAuditLogResource(BaseIdentifiableInterface $entity): void
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request) {
            return;
        }
        AuditLogResourceHelper::addResourceByEntity($request, $entity);
    }
}
