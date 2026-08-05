<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Resolver;

use AnzuSystems\CommonBundle\Helper\StringHelper;
use AnzuSystems\CommonBundle\Mcp\Exception\McpToolInputException;
use Symfony\Component\Uid\Uuid;

final readonly class McpContextIdResolver
{
    public function resolve(string $contextId): string
    {
        $contextId = trim($contextId);
        if (Uuid::isValid($contextId)) {
            return Uuid::fromString($contextId)->toRfc4122();
        }

        throw new McpToolInputException(
            sprintf('Invalid contextId "%s", provide a UUID taken from a log record.', $contextId),
        );
    }

    public function resolveOptional(?string $contextId): ?string
    {
        if (null === $contextId) {
            return null;
        }
        if (StringHelper::isEmpty(trim($contextId))) {
            return null;
        }

        return $this->resolve($contextId);
    }
}
