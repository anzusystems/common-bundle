<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Log\McpLogFinder;
use AnzuSystems\CommonBundle\Mcp\McpToolExecutor;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpContextIdResolver;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

#[McpTool(
    name: SearchAppLogsTool::NAME,
    description: 'Searches the application log of this service: runtime records with a level, a message, and a '
        . 'contextId linking each record to the request that produced it. Filter by level (e.g. "ERROR"), a '
        . 'case-insensitive message substring, or a contextId taken from an audit log record. Diagnostic workflow: '
        . 'find the failed request via search_audit_logs first, then look up the application errors behind it either '
        . 'here by contextId or with get_logs_by_context; the same contextId correlates logs across the services of '
        . 'this platform. The time window defaults to the last day and is capped at 31 days; results are newest first '
        . 'and long messages are truncated.',
)]
final readonly class SearchAppLogsTool
{
    public const string NAME = 'search_app_logs';

    private const string HINT_CONTEXT_ID = 'Pass a record\'s contextId to get_logs_by_context to see the request and '
        . 'MCP tool calls behind it; the same contextId correlates logs across the services of this platform.';

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
        #[Schema(description: 'Log level name, e.g. "ERROR", "WARNING", "INFO". Case-insensitive, exact match. Omit for all levels.')]
        ?string $level = null,
        #[Schema(description: 'Case-insensitive substring match on the log message.')]
        ?string $messageContains = null,
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
                'level' => $level,
                'messageContains' => $messageContains,
                'contextId' => $contextId,
                'from' => $from,
                'until' => $until,
                'limit' => $limit,
            ],
            fn (): array => [
                'appLogs' => $this->logFinder->findAppLogs(
                    level: $level,
                    messageContains: $messageContains,
                    contextId: $this->contextIdResolver->resolveOptional($contextId),
                    from: $from,
                    until: $until,
                    limit: $limit,
                ),
                'hint' => self::HINT_CONTEXT_ID,
            ],
        );
    }
}
