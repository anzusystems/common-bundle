<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class ArrayStringParam
{
    public function __construct(
        public ?int $itemsLimit = null,
        /**
         * @var non-empty-string|null
         */
        public ?string $itemNormalizer = null,
        /**
         * @var non-empty-string
         */
        public string $separator = ',',
        /**
         * @var int[]
         */
        public array $limitAllowList = [],
    ) {
    }
}
