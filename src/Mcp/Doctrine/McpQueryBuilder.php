<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;

final class McpQueryBuilder extends QueryBuilder
{
    public function __construct(
        EntityManagerInterface $entityManager,
        private readonly int $maxExecutionTimeMs,
    ) {
        parent::__construct($entityManager);
    }

    public function getQuery(): Query
    {
        return parent::getQuery()
            ->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, McpMaxExecutionTimeWalker::class)
            ->setHint(McpMaxExecutionTimeWalker::HINT_MAX_EXECUTION_TIME_MS, $this->maxExecutionTimeMs)
        ;
    }
}
