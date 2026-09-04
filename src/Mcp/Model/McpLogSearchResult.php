<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model;

final readonly class McpLogSearchResult
{
    /**
     * @param list<array<string, mixed>> $logs
     */
    public function __construct(
        public array $logs,
        public McpDateWindow $window,
        public int $limit,
        public bool $hasMore,
    ) {
    }
}
