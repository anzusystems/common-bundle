<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Response;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Serializer\Handler\Handlers\RawArrayHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class McpAuditLogSearchResponse
{
    use McpLogSearchResponseTrait;

    private const string HINT_CONTEXT_ID = 'Pass a record\'s contextId to get_logs_by_context to see the application '
        . 'errors and MCP tool calls behind that request; the same contextId correlates logs across the services of '
        . 'this platform.';

    /**
     * @var list<array<string, mixed>>
     */
    #[McpField(description: 'Newest first: one record per request with method, path, acting userId, resource name and ids, http status, request body (content), response, error and exception details and a contextId. Long fields are truncated.')]
    #[Serialize(handler: RawArrayHandler::class)]
    public array $auditLogs {
        get => $this->result->logs;
    }
}
