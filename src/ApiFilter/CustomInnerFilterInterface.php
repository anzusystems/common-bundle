<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\ApiFilter;

use Doctrine\ORM\QueryBuilder;

interface CustomInnerFilterInterface
{
    /**
     * On which field should the filter be applied
     */
    public function field(): string;

    /**
     * Filter should return true, if it was applied to prevent from being applied from query
     */
    public function apply(QueryBuilder $dqb, bool $isset): bool;
}
