<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\AbstractMcpTool;
use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Model\Request\SearchAppLogsRequest;
use Closure;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\RequestContext;

/**
 * @extends AbstractMcpTool<SearchAppLogsRequest>
 */
#[AsMcpTool(
    name: ExampleSearchTool::NAME,
    description: 'Searches examples.',
    input: SearchAppLogsRequest::class,
    readOnly: true,
)]
final readonly class ExampleSearchTool extends AbstractMcpTool
{
    public const string NAME = 'example_search';

    /**
     * @param Closure(SearchAppLogsRequest): (object|array<string, mixed>) $handler
     */
    public function __construct(
        private Closure $handler,
    ) {
    }

    public function __invoke(RequestContext $context): CallToolResult
    {
        return $this->execute($context);
    }

    protected function handle(object $input): object|array
    {
        return ($this->handler)($input);
    }
}
