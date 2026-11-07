<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExampleWriteRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response\UnserializableExampleResponse;
use Closure;

#[AsMcpTool(
    name: UnserializableResponseWriteTool::NAME,
    description: 'Writes an example and returns a response that cannot be serialized.',
    category: ExampleToolCategory::EXAMPLES,
    permission: ExampleWriteTool::PERMISSION,
)]
final readonly class UnserializableResponseWriteTool
{
    public const string NAME = 'unserializable_response_write';

    /**
     * @param Closure(): UnserializableExampleResponse $handler
     */
    public function __construct(
        private Closure $handler,
    ) {
    }

    public function __invoke(ExampleWriteRequest $request): UnserializableExampleResponse
    {
        return ($this->handler)();
    }
}
