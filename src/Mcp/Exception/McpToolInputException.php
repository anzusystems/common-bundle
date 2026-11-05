<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Exception;

use RuntimeException;

/**
 * An argument the client can correct. A subclass builds its messages in static create*() methods.
 */
abstract class McpToolInputException extends RuntimeException
{
    final protected function __construct(string $message)
    {
        parent::__construct($message);
    }
}
