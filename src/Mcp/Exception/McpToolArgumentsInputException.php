<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Exception;

final class McpToolArgumentsInputException extends McpToolInputException
{
    /**
     * @param list<string> $unknownArguments
     * @param list<string> $allowedArguments
     */
    public static function createUnknownArguments(array $unknownArguments, array $allowedArguments): self
    {
        return new self(sprintf(
            'Unknown argument%s "%s". Allowed arguments: %s.',
            1 === count($unknownArguments) ? '' : 's',
            implode('", "', $unknownArguments),
            implode(', ', $allowedArguments),
        ));
    }

    public static function createInvalidArguments(string $violationsMessage): self
    {
        return new self($violationsMessage);
    }
}
