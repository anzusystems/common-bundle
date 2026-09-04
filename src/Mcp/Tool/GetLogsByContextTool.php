<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Log\McpLogFinder;
use AnzuSystems\CommonBundle\Mcp\McpToolExecutor;
use AnzuSystems\CommonBundle\Mcp\Model\Request\GetLogsByContextRequest;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\Result\CallToolResult;

#[McpTool(
    name: GetLogsByContextTool::NAME,
    description: 'Returns all logs correlated to one request by its contextId — a UUID present on every audit log '
        . 'record, application log record, and MCP tool call. The response contains auditLogs (the API requests), '
        . 'appLogs (application records produced while handling them), and mcpToolCalls, each newest first. '
        . 'Diagnostic workflow: after search_audit_logs finds a failed request, call this tool with its contextId to '
        . 'see the application errors behind the failure. The same contextId correlates logs across the services of '
        . 'this platform, so it can also be looked up in the logs of the other services involved in the request. Long '
        . 'fields are truncated; only logs from the last 31 days are searched.',
)]
final readonly class GetLogsByContextTool
{
    public const string NAME = 'get_logs_by_context';

    public function __construct(
        private McpLogFinder $logFinder,
        private McpToolExecutor $toolExecutor,
    ) {
    }

    #[Schema(additionalProperties: false)]
    public function __invoke(
        #[Schema(description: 'The contextId (UUID) of one request, taken from an audit log, application log, or MCP tool call record.')]
        string $contextId,
    ): CallToolResult {
        $request = new GetLogsByContextRequest(contextId: $contextId);

        return $this->toolExecutor->execute(
            self::NAME,
            $request,
            fn (): McpLogsByContextResponse => new McpLogsByContextResponse($this->logFinder->findLogsByContext($request)),
        );
    }
}
