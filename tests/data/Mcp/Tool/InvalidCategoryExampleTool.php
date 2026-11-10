<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use Closure;

#[AsMcpTool(
    name: InvalidCategoryExampleTool::NAME,
    description: 'Lists examples of a category that is not camelCase.',
    category: InvalidCategoryExampleTool::CATEGORY,
    readOnly: true,
)]
final readonly class InvalidCategoryExampleTool
{
    public const string NAME = 'invalid_category_example';
    public const string CATEGORY = 'example.tools';

    /**
     * @param Closure(): McpLogsByContextResponse $handler
     */
    public function __construct(
        private Closure $handler,
    ) {
    }

    public function __invoke(): McpLogsByContextResponse
    {
        return ($this->handler)();
    }
}
