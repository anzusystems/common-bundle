<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model;

final readonly class McpPageWindow
{
    public const int PAGE_MIN = 1;
    public const int LIMIT_MIN = 1;

    public function __construct(
        public int $page,
        public int $limit,
        public int $requestedPage,
        public int $requestedLimit,
        public int $pageMax,
        public int $limitMax,
    ) {
    }

    public function isClamped(): bool
    {
        return false === ($this->page === $this->requestedPage && $this->limit === $this->requestedLimit);
    }

    public function isRaisedToMinimum(): bool
    {
        return $this->requestedPage < self::PAGE_MIN || $this->requestedLimit < self::LIMIT_MIN;
    }

    public function getOffset(): int
    {
        return ($this->page - self::PAGE_MIN) * $this->limit;
    }

    public function getReachableRows(): int
    {
        return $this->pageMax * $this->limit;
    }
}
