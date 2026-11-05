<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Response;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Mcp\Model\Decorator\McpAuditLogDecorator;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\Collection;

final class McpAuditLogSearchResponse
{
    /**
     * @use McpLogSearchResponseTrait<McpAuditLogDecorator>
     */
    use McpLogSearchResponseTrait;

    private const string HINT_CONTEXT_ID = 'Pass a record\'s contextId to get_logs_by_context to see the application '
        . 'errors and MCP tool calls behind that request; the same contextId correlates logs across the services of '
        . 'this platform.';

    /**
     * @var Collection<int, McpAuditLogDecorator>
     */
    #[McpField(description: 'Newest first: one record per request.')]
    #[Serialize(type: McpAuditLogDecorator::class)]
    public Collection $auditLogs {
        get => $this->result->logs;
    }
}
