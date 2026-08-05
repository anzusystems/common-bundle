<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model;

final readonly class McpAuditLogFilter
{
    public function __construct(
        public ?int $userId,
        public bool $onlyErrors,
        public ?string $pathContains,
        public ?string $resourceName,
        public ?string $contextId,
        public ?string $from,
        public ?string $until,
        public int $limit,
    ) {
    }
}
