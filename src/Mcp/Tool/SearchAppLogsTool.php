<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Log\McpLogFinder;
use AnzuSystems\CommonBundle\Mcp\McpToolExecutor;
use AnzuSystems\CommonBundle\Mcp\Model\Request\SearchAppLogsRequest;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpAppLogSearchResponse;
use Mcp\Capability\Attribute\McpTool;

use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\Result\CallToolResult;

#[McpTool(
    name: SearchAppLogsTool::NAME,
    description: 'Searches the application log of this service: runtime records with a level, a message, and a '
        . 'contextId linking each record to the request that produced it. Filter by level (e.g. "ERROR"), a '
        . 'case-insensitive message substring, or a contextId taken from an audit log record. Diagnostic workflow: '
        . 'find the failed request via search_audit_logs first, then look up the application errors behind it either '
        . 'here by contextId or with get_logs_by_context; the same contextId correlates logs across the services of '
        . 'this platform. The time window defaults to the last day and is capped at 31 days: the response echoes the '
        . 'effective from and until, and a longer requested window is shortened and reported in warnings — '
        . 'repeat the search on the remaining days. Results are newest first and long messages are truncated.',
)]
final readonly class SearchAppLogsTool
{
    public const string NAME = 'search_app_logs';

    public function __construct(
        private McpLogFinder $logFinder,
        private McpToolExecutor $toolExecutor,
    ) {
    }

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
    ): CallToolResult {
        $request = new SearchAppLogsRequest(
            level: $level,
            messageContains: $messageContains,
            contextId: $contextId,
            from: $from,
            until: $until,
            limit: $limit,
        );

        return $this->toolExecutor->execute(
            self::NAME,
            $request,
            fn (): McpAppLogSearchResponse => new McpAppLogSearchResponse($this->logFinder->findAppLogs($request)),
        );
    }
}
