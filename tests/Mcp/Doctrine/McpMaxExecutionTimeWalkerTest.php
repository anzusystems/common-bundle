<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Doctrine;

use AnzuSystems\CommonBundle\Mcp\Doctrine\McpMaxExecutionTimeWalker;
use AnzuSystems\CommonBundle\Mcp\Doctrine\McpQueryBuilder;
use AnzuSystems\CommonBundle\Tests\Data\Entity\Example;
use AnzuSystems\CommonBundle\Tests\Doctrine\AbstractDoctrineQueryTestCase;
use Doctrine\ORM\Query;
use UnexpectedValueException;

final class McpMaxExecutionTimeWalkerTest extends AbstractDoctrineQueryTestCase
{
    private const int MAX_EXECUTION_TIME_MS = 1_500;

    public function testQueryBuilderAddsTheMaxExecutionTimeHint(): void
    {
        $sql = new McpQueryBuilder(entityManager: $this->entityManager, maxExecutionTimeMs: self::MAX_EXECUTION_TIME_MS)
            ->select('e.name')
            ->from(Example::class, 'e')
            ->getQuery()
            ->getSQL();

        self::assertSame(
            sprintf('SELECT /*+ MAX_EXECUTION_TIME(%d) */ e0_.name AS name_0 FROM example e0_', self::MAX_EXECUTION_TIME_MS),
            $sql,
        );
    }

    public function testNonIntHintFails(): void
    {
        $this->query
            ->setDQL(sprintf('SELECT e.name FROM %s e', Example::class))
            ->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, McpMaxExecutionTimeWalker::class)
            ->setHint(McpMaxExecutionTimeWalker::HINT_MAX_EXECUTION_TIME_MS, (string) self::MAX_EXECUTION_TIME_MS);

        $this->expectException(UnexpectedValueException::class);

        $this->query->getSQL();
    }
}
