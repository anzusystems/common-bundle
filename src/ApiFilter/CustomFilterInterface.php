<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\ApiFilter;

use Doctrine\ORM\QueryBuilder;

interface CustomFilterInterface
{
    public function apply(QueryBuilder $dqb, string $field, string | int $value): QueryBuilder;
}
