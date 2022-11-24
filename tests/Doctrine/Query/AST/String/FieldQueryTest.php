<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Doctrine\Query\AST\String;

use AnzuSystems\CommonBundle\Tests\Data\Entity\Example;
use AnzuSystems\CommonBundle\Tests\Doctrine\AbstractDoctrineQueryTestCase;

final class FieldQueryTest extends AbstractDoctrineQueryTestCase
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
                'SELECT e.name FROM ' . Example::class . ' e ORDER BY FIELD(e.id, 1)',
                'SELECT e0_.name AS name_0 FROM example e0_ ORDER BY FIELD(e0_.id, 1) ASC',
            ],
            [
                'SELECT e.name FROM ' . Example::class . ' e ORDER BY FIELD(e.id, 1, 2, 3, 4)',
                'SELECT e0_.name AS name_0 FROM example e0_ ORDER BY FIELD(e0_.id, 1, 2, 3, 4) ASC',
            ],
            [
                'SELECT e.name FROM ' . Example::class . ' e ORDER BY FIELD(e.id, :ids)',
                'SELECT e0_.name AS name_0 FROM example e0_ ORDER BY FIELD(e0_.id, ?) ASC',
            ],
        ];
    }
}
