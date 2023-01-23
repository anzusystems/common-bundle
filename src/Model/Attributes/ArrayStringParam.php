<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class ArrayStringParam
{
    public function __construct(
        public ?int $itemsLimit = null,
        public ?string $itemNormalizer = null,
        public string $separator = ',',
    ) {
    }
}
