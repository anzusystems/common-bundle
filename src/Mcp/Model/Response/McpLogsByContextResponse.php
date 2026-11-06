<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Response;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Mcp\Model\Decorator\McpAppLogDecorator;
use AnzuSystems\CommonBundle\Mcp\Model\Decorator\McpAuditLogDecorator;
use AnzuSystems\CommonBundle\Mcp\Model\Decorator\McpToolCallLogDecorator;
use AnzuSystems\CommonBundle\Mcp\Model\McpLogsByContextResult;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\Collection;

final class McpLogsByContextResponse
{
    /**
     * @var Collection<int, McpAuditLogDecorator>
     */
    #[McpField(description: 'The API requests, newest first.')]
    #[Serialize(type: McpAuditLogDecorator::class)]
    public Collection $auditLogs {
        get => $this->result->auditLogs;
    }

    /**
     * @var Collection<int, McpAppLogDecorator>
     */
    #[McpField(description: 'Application records produced while handling them, newest first.')]
    #[Serialize(type: McpAppLogDecorator::class)]
    public Collection $appLogs {
        get => $this->result->appLogs;
    }

    /**
     * @var Collection<int, McpToolCallLogDecorator>
     */
    #[McpField(description: 'MCP tool calls with this contextId, newest first.')]
    #[Serialize(type: McpToolCallLogDecorator::class)]
    public Collection $mcpToolCalls {
        get => $this->result->mcpToolCalls;
    }

    public function __construct(
        private readonly McpLogsByContextResult $result,
    ) {
    }
}
