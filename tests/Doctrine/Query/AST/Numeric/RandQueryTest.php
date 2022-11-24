<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Doctrine\Query\AST\Numeric;

use AnzuSystems\CommonBundle\Tests\Data\Entity\Example;
use AnzuSystems\CommonBundle\Tests\Doctrine\AbstractDoctrineQueryTestCase;

final class RandQueryTest extends AbstractDoctrineQueryTestCase
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
                'SELECT RAND() FROM ' . Example::class . ' e',
                'SELECT RAND() AS sclr_0 FROM example e0_',
            ],
            [
                'SELECT e.name FROM ' . Example::class . ' e ORDER BY RAND()',
                'SELECT e0_.name AS name_0 FROM example e0_ ORDER BY RAND() ASC',
            ],
        ];
    }
}
