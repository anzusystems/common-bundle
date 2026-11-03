<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Model\McpLogsByContextResult;
use AnzuSystems\CommonBundle\Mcp\Model\Request\SearchAppLogsRequest;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;

#[AsMcpTool(
    name: InvalidParameterExampleTool::NAME,
    description: 'Searches examples of a site.',
    category: ExampleToolCategory::EXAMPLES,
    readOnly: true,
)]
final readonly class InvalidParameterExampleTool
{
    public const string NAME = 'invalid_parameter_example';

    public function __invoke(SearchAppLogsRequest $request, int $siteId): McpLogsByContextResponse
    {
        return new McpLogsByContextResponse(new McpLogsByContextResult([], [], []));
    }
}
