<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Helper;

final class MathHelper
{
    /**
     * Compare two floats.
     */
    public static function floatEquals(float $valA, float $valB): bool
    {
        return abs($valA - $valB) < PHP_FLOAT_EPSILON;
    }
}
