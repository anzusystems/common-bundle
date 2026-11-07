<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Pagination\Keyset;

use Closure;
use Doctrine\Common\Collections\Collection;

/**
 * @template T of object
 */
final readonly class KeysetPage
{
    /**
     * @param Collection<int, T> $items
     */
    public function __construct(
        public Collection $items,
        public ?string $nextCursor,
    ) {
    }

    /**
     * @template U of object
     *
     * @param Closure(T): U $mapper
     *
     * @return self<U>
     */
    public function map(Closure $mapper): self
    {
        return new self(
            items: $this->items->map($mapper),
            nextCursor: $this->nextCursor,
        );
    }
}
