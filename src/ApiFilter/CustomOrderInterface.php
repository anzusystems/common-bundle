<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\ApiFilter;

use Doctrine\ORM\QueryBuilder;

interface CustomOrderInterface
{
    public function apply(QueryBuilder $dqb, ApiParams $apiParams): QueryBuilder;
}
