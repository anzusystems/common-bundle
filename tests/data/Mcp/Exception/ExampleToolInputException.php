<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Exception;

use AnzuSystems\CommonBundle\Mcp\Exception\McpToolInputException;

final class ExampleToolInputException extends McpToolInputException
{
    public static function createExample(string $message): self
    {
        return new self($message);
    }
}
