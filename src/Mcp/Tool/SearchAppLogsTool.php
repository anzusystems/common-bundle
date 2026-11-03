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
        TXT
    ,
    values: [
        'get_logs_by_context' => GetLogsByContextTool::NAME,
        'search_audit_logs' => SearchAuditLogsTool::NAME,
    ],
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
