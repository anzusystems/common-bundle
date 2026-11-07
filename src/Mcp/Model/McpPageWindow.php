<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model;

final class McpPageWindow
{
    public const int PAGE_MIN = 1;
    public const int PAGE_MAX = 200;
    public const int LIMIT_MIN = 1;

    public int $offset {
        get => ($this->page - self::PAGE_MIN) * $this->limit;
    }

    public function __construct(
        public readonly int $page,
        public readonly int $limit,
    ) {
    }
}
