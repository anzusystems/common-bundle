<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Log\McpLogFinder;
use AnzuSystems\CommonBundle\Mcp\McpToolExecutor;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpContextIdResolver;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

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

    private const string HINT_CROSS_SERVICE = 'The same contextId correlates logs across the services of this '
        . 'platform; look it up in the logs of the other services to follow the request end to end.';

    public function __construct(
        private McpLogFinder $logFinder,
        private McpContextIdResolver $contextIdResolver,
        private McpToolExecutor $toolExecutor,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[Schema(additionalProperties: false)]
    public function __invoke(
        #[Schema(description: 'The contextId (UUID) of one request, taken from an audit log, application log, or MCP tool call record.')]
        string $contextId,
    ): array {
        return $this->toolExecutor->execute(
            self::NAME,
            ['contextId' => $contextId],
            fn (): array => $this->getLogsByContext($this->contextIdResolver->resolve($contextId)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getLogsByContext(string $contextId): array
    {
        return [
            'auditLogs' => $this->logFinder->findAuditLogsByContextId($contextId),
            'appLogs' => $this->logFinder->findAppLogsByContextId($contextId),
            'mcpToolCalls' => $this->logFinder->findMcpLogsByContextId($contextId),
            'hint' => self::HINT_CROSS_SERVICE,
        ];
    }
}
