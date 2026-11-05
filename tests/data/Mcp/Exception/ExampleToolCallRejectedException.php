<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Exception;

use AnzuSystems\CommonBundle\Mcp\Exception\McpToolCallRejectedException;

final class ExampleToolCallRejectedException extends McpToolCallRejectedException
{
    public static function createExample(string $message): self
    {
        return new self($message);
    }
}
