<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model;

use DateTimeImmutable;

final readonly class McpDateWindow
{
    public function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $until,
    ) {
    }
}
