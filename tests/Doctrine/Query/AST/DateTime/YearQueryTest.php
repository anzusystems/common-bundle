<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Doctrine\Query\AST\DateTime;

use AnzuSystems\CommonBundle\Tests\Data\Entity\Example;
use AnzuSystems\CommonBundle\Tests\Doctrine\AbstractDoctrineQueryTestCase;

final class YearQueryTest extends AbstractDoctrineQueryTestCase
{
    /**
     * @dataProvider dqlProvider
     */
    public function testYearFunction(string $dql, string $expectedSql): void
    {
        $this->query->setDQL($dql);
        self::assertSame($expectedSql, $this->query->getSQL());
    }

    public function dqlProvider(): array
    {
        return [
            [
                'SELECT YEAR(e.createdAt) FROM ' . Example::class . ' e',
                'SELECT YEAR(e0_.created_at) AS sclr_0 FROM example e0_',
            ],
            [
                'SELECT YEAR(e.createdAt), e.name FROM ' . Example::class . ' e',
                'SELECT YEAR(e0_.created_at) AS sclr_0, e0_.name AS name_1 FROM example e0_',
            ],
            [
                'SELECT YEAR(e.createdAt), e.name FROM ' . Example::class . ' e WHERE YEAR(e.createdAt) > 100',
                'SELECT YEAR(e0_.created_at) AS sclr_0, e0_.name AS name_1 FROM example e0_ WHERE YEAR(e0_.created_at) > 100',
            ],
        ];
    }
}
