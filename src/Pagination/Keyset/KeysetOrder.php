<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Pagination\Keyset;

use Doctrine\Common\Collections\Order;

final readonly class KeysetOrder
{
    /**
     * @param non-empty-list<string> $fields fields of the root entity in the order of the keyset, the last one unique
     *                                       (usually id), so rows with the same leading values are neither repeated nor skipped
     */
    public function __construct(
        public array $fields,
        public Order $direction,
    ) {
    }
}
