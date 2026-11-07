<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Exception;

use RuntimeException;

abstract class McpToolCallRejectedException extends RuntimeException
{
    final protected function __construct(string $message)
    {
        parent::__construct($message);
    }
}
