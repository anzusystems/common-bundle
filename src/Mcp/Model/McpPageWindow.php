<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model;

final class McpPageWindow
{
    public const int PAGE_MIN = 1;
    public const int LIMIT_MIN = 1;

    public bool $clamped {
        get => false === ($this->page === $this->requestedPage && $this->limit === $this->requestedLimit);
    }
    public bool $raisedToMinimum {
        get => $this->requestedPage < self::PAGE_MIN || $this->requestedLimit < self::LIMIT_MIN;
    }
    public int $offset {
        get => ($this->page - self::PAGE_MIN) * $this->limit;
    }
    public int $reachableRows {
        get => $this->pageMax * $this->limit;
    }

    public function __construct(
        public readonly int $page,
        public readonly int $limit,
        public readonly int $requestedPage,
        public readonly int $requestedLimit,
        public readonly int $pageMax,
        public readonly int $limitMax,
    ) {
    }
}
