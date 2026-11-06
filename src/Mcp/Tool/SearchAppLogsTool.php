<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogFinder;
use AnzuSystems\CommonBundle\Mcp\Model\Request\SearchAppLogsRequest;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpAppLogSearchResponse;

#[AsMcpTool(
    name: SearchAppLogsTool::NAME,
    description: <<<'TXT'
        Searches the application log of this service: runtime records with a level, a message and the contextId of the request that produced them.
        Find the failed request with {{ search_audit_logs }} first, then look up the application errors behind it here by contextId or with {{ get_logs_by_context }}.
        Pass a record's contextId to {{ get_logs_by_context }} to see the request and MCP tool calls behind it; the same contextId correlates logs across the services of this platform.
        TXT
    ,
    descriptionValues: [
        'get_logs_by_context' => GetLogsByContextTool::NAME,
        'search_audit_logs' => SearchAuditLogsTool::NAME,
    ],
    category: AsMcpTool::CATEGORY_DIAGNOSTICS,
    permission: AsMcpTool::PERMISSION_LOG_TOOLS,
    readOnly: true,
)]
final readonly class SearchAppLogsTool
{
    public const string NAME = 'search_app_logs';

    public function __construct(
        private McpLogFinder $logFinder,
    ) {
    }

    public function __invoke(SearchAppLogsRequest $request): McpAppLogSearchResponse
    {
        return new McpAppLogSearchResponse($this->logFinder->findAppLogs($request));
    }
}
