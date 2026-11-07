<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Exception;

use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolErrorResolver;

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

    public static function createInvalidArgument(string $propertyPath, string $message): self
    {
        return new self(McpToolErrorResolver::INVALID_ARGUMENTS_PREFIX . sprintf(McpToolErrorResolver::VIOLATION_FORMAT, $propertyPath, $message));
    }
}
