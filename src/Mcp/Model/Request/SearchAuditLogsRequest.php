<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Request;

use AnzuSystems\CommonBundle\Mcp\Log\McpLogFinder;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SearchAuditLogsRequest
{
    public function __construct(
        #[Serialize]
        public ?int $userId,
        #[Serialize]
        public bool $onlyErrors,
        #[Serialize]
        public ?string $pathContains,
        #[Serialize]
        public ?string $resourceName,
        #[Serialize]
        public ?string $contextId,
        #[Serialize]
        public ?string $from,
        #[Serialize]
        public ?string $until,
        #[Serialize]
        #[Assert\GreaterThanOrEqual(value: McpLogFinder::LIMIT_MIN, message: McpLogFinder::LIMIT_MIN_MESSAGE)]
        public int $limit,
    ) {
    }
}
