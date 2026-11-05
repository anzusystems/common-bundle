<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogFinder;
use AnzuSystems\CommonBundle\Mcp\Model\Request\GetLogsByContextRequest;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;

#[AsMcpTool(
    name: GetLogsByContextTool::NAME,
    description: <<<'TXT'
        Returns all logs correlated to one request by its contextId: a UUID present on every audit log record, application log record and MCP tool call.
        The same contextId correlates the logs of the other services of this platform, so it can also be looked up there.
        TXT
    ,
    category: AsMcpTool::CATEGORY_DIAGNOSTICS,
    permission: AsMcpTool::PERMISSION_LOG_TOOLS,
    readOnly: true,
)]
final readonly class GetLogsByContextTool
{
    public const string NAME = 'get_logs_by_context';

    public function __construct(
        private McpLogFinder $logFinder,
    ) {
    }

    public function __invoke(GetLogsByContextRequest $request): McpLogsByContextResponse
    {
        return new McpLogsByContextResponse($this->logFinder->findLogsByContext($request));
    }
}
