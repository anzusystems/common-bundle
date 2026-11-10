<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model;

use AnzuSystems\CommonBundle\Mcp\Model\Decorator\AbstractMcpLogDecorator;
use Doctrine\Common\Collections\Collection;

/**
 * @template T of AbstractMcpLogDecorator
 */
final readonly class McpLogSearchResult
{
    /**
     * @param Collection<int, T> $logs
     */
    public function __construct(
        public Collection $logs,
        public McpDateWindow $window,
        public int $limit,
        public bool $hasMore,
    ) {
    }
}
