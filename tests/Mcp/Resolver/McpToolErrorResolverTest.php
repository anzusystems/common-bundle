<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Resolver;

use AnzuSystems\CommonBundle\Mcp\Exception\McpToolArgumentsInputException;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolErrorResolver;
use AnzuSystems\CommonBundle\Pagination\Keyset\Exception\InvalidCursorException;
use PHPUnit\Framework\TestCase;

final class McpToolErrorResolverTest extends TestCase
{
    public function testInvalidCursorIsAnInvalidCursorArgument(): void
    {
        self::assertSame(
            McpToolArgumentsInputException::createInvalidArgument(
                propertyPath: McpToolErrorResolver::CURSOR_ARGUMENT,
                message: McpToolErrorResolver::INVALID_CURSOR_MESSAGE,
            )->getMessage(),
            new McpToolErrorResolver()
                ->resolveRejectionMessage(new InvalidCursorException()),
        );
    }
}
