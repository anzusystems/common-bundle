<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Exception;

use RuntimeException;

/**
 * A call a guard refused. A subclass builds its messages in static create*() methods.
 */
abstract class McpToolCallRejectedException extends RuntimeException
{
    final protected function __construct(string $message)
    {
        parent::__construct($message);
    }
}
