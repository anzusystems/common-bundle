<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\AbstractMcpTool;
use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use Closure;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\RequestContext;
use stdClass;

/**
 * @extends AbstractMcpTool<stdClass>
 */
#[AsMcpTool(
    name: ExampleWithoutInputTool::NAME,
    description: 'Creates an example.',
)]
final readonly class ExampleWithoutInputTool extends AbstractMcpTool
{
    public const string NAME = 'example_without_input';

    /**
     * @param Closure(): (object|array<string, mixed>) $handler
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
        return ($this->handler)();
    }
}
