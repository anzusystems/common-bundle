<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogFinder;
use AnzuSystems\CommonBundle\Mcp\Model\Request\SearchAuditLogsRequest;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpAuditLogSearchResponse;

#[AsMcpTool(
    name: SearchAuditLogsTool::NAME,
    description: <<<'TXT'
        Searches the audit log of the API requests handled by this service; by default only failed requests.
        When a user reports a failed action (e.g. a record that could not be saved), search with their userId and a time window around the incident, then pass the contextId of the failing record to {{ get_logs_by_context }} to see the application errors behind it.
        TXT
    ,
    descriptionValues: ['get_logs_by_context' => GetLogsByContextTool::NAME],
    category: AsMcpTool::CATEGORY_DIAGNOSTICS,
    permission: AsMcpTool::PERMISSION_LOG_TOOLS,
    readOnly: true,
)]
final readonly class SearchAuditLogsTool
{
    public const string NAME = 'search_audit_logs';

    public function __construct(
        private McpLogFinder $logFinder,
    ) {
    }

    public function __invoke(SearchAuditLogsRequest $request): McpAuditLogSearchResponse
    {
        return new McpAuditLogSearchResponse($this->logFinder->findAuditLogs($request));
    }
}
