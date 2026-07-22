<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Log\McpLogFinder;
use AnzuSystems\CommonBundle\Mcp\McpToolExecutor;
use AnzuSystems\CommonBundle\Mcp\Model\McpAuditLogFilter;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpContextIdResolver;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

#[McpTool(
    name: SearchAuditLogsTool::NAME,
    description: 'Searches the audit log of API requests handled by this service. Each record describes one request: '
        . 'method, path, acting userId, resource name and ids, http status, request body (content), response, error '
        . 'and exception details, and a contextId. By default only failed requests are returned (http status 400 or '
        . 'higher, or a non-empty error/exception) — set onlyErrors to false to include successful requests. '
        . 'Diagnostic workflow: when a user reports a failed action (e.g. could not save a record), search with '
        . 'their userId, onlyErrors, and a time window around the incident, then pass the failing record\'s contextId '
        . 'to get_logs_by_context to see the application errors behind it; the same contextId correlates logs across '
        . 'the services of this platform. The time window defaults to the last day and is capped at 31 days; results '
        . 'are newest first and long fields are truncated.',
)]
final readonly class SearchAuditLogsTool
{
    public const string NAME = 'search_audit_logs';

    private const string HINT_CONTEXT_ID = 'Pass a record\'s contextId to get_logs_by_context to see the application '
        . 'errors and MCP tool calls behind that request; the same contextId correlates logs across the services of '
        . 'this platform.';

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
        #[Schema(description: 'Filter by the id of the user who made the request. Omit to search requests of all users.')]
        ?int $userId = null,
        #[Schema(description: 'When true (default), only failed requests are returned: http status 400 or higher, or a non-empty error/exception. Set to false to include successful requests.')]
        bool $onlyErrors = true,
        #[Schema(description: 'Case-insensitive substring match on the request path, e.g. "article".')]
        ?string $pathContains = null,
        #[Schema(description: 'Exact resource name of the affected entity as stored in the audit record, e.g. "articleKindStandard".')]
        ?string $resourceName = null,
        #[Schema(description: 'Exact contextId (UUID) of one request, as found in other log records.')]
        ?string $contextId = null,
        #[Schema(description: 'Only records at or after this ISO 8601 date-time, e.g. "2026-07-20T06:00:00+02:00". Defaults to 1 day before until.')]
        ?string $from = null,
        #[Schema(description: 'Only records at or before this ISO 8601 date-time. Defaults to now; the from..until window is capped at 31 days.')]
        ?string $until = null,
        #[Schema(description: 'Maximum number of records, capped at 50.')]
        int $limit = McpLogFinder::LIMIT_DEFAULT,
    ): array {
        return $this->toolExecutor->execute(
            self::NAME,
            [
                'userId' => $userId,
                'onlyErrors' => $onlyErrors,
                'pathContains' => $pathContains,
                'resourceName' => $resourceName,
                'contextId' => $contextId,
                'from' => $from,
                'until' => $until,
                'limit' => $limit,
            ],
            fn (): array => [
                'auditLogs' => $this->logFinder->findAuditLogs(new McpAuditLogFilter(
                    userId: $userId,
                    onlyErrors: $onlyErrors,
                    pathContains: $pathContains,
                    resourceName: $resourceName,
                    contextId: $this->contextIdResolver->resolveOptional($contextId),
                    from: $from,
                    until: $until,
                    limit: $limit,
                )),
                'hint' => self::HINT_CONTEXT_ID,
            ],
        );
    }
}
