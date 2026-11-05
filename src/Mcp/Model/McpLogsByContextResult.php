<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model;

use AnzuSystems\CommonBundle\Mcp\Model\Decorator\McpAppLogDecorator;
use AnzuSystems\CommonBundle\Mcp\Model\Decorator\McpAuditLogDecorator;
use AnzuSystems\CommonBundle\Mcp\Model\Decorator\McpToolCallLogDecorator;
use Doctrine\Common\Collections\Collection;

final readonly class McpLogsByContextResult
{
    /**
     * @param Collection<int, McpAuditLogDecorator> $auditLogs
     * @param Collection<int, McpAppLogDecorator> $appLogs
     * @param Collection<int, McpToolCallLogDecorator> $mcpToolCalls
     */
    public function __construct(
        public Collection $auditLogs,
        public Collection $appLogs,
        public Collection $mcpToolCalls,
    ) {
    }
}
