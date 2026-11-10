<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Model\Request\SearchAppLogsRequest;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use Closure;

#[AsMcpTool(
    name: ExampleSearchTool::NAME,
    description: 'Searches examples.',
    category: ExampleToolCategory::EXAMPLES,
    readOnly: true,
)]
final readonly class ExampleSearchTool
{
    public const string NAME = 'example_search';

    /**
     * @param Closure(SearchAppLogsRequest): McpLogsByContextResponse $handler
     */
    public function __construct(
        private Closure $handler,
    ) {
    }

    public function __invoke(SearchAppLogsRequest $request): McpLogsByContextResponse
    {
        return ($this->handler)($request);
    }
}
