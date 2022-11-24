<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Doctrine\Walker;

use AnzuSystems\CommonBundle\Doctrine\Walker\ForceIndexWalker;
use AnzuSystems\CommonBundle\Tests\Data\Entity\Example;
use AnzuSystems\CommonBundle\Tests\Data\Entity\ExampleJoinedEntity;
use AnzuSystems\CommonBundle\Tests\Doctrine\AbstractDoctrineQueryTestCase;
use Doctrine\ORM\Query;

final class ForceIndexWalkerQueryTest extends AbstractDoctrineQueryTestCase
{
    /**
     * @dataProvider dqlProvider
     */
    public function testForceIndexWalkerFunction(
        string $dql,
        string $type,
        string | array $index,
        string $expectedSql
    ): void {
        $this->query
            ->setDQL($dql)
            ->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, ForceIndexWalker::class)
            ->setHint($type, $index);

        self::assertSame($expectedSql, $this->query->getSQL());
    }

    public function dqlProvider(): array
    {
        return [
            [
                'SELECT e.name FROM ' . Example::class . ' e',
                ForceIndexWalker::HINT_FORCE_INDEX_FOR_FROM,
                Example::IDX_JOINED_ENTITY,
                'SELECT e0_.name AS name_0 FROM example e0_ FORCE INDEX (' . Example::IDX_JOINED_ENTITY . ')',
            ],
            [
                'SELECT e.name FROM ' . Example::class . ' e WHERE e.id > 0',
                ForceIndexWalker::HINT_FORCE_INDEX_FOR_FROM,
                Example::IDX_JOINED_ENTITY,
                'SELECT e0_.name AS name_0 FROM example e0_ FORCE INDEX (' . Example::IDX_JOINED_ENTITY . ') WHERE e0_.id > 0',
            ],
            [
                'SELECT e.name FROM ' . Example::class . ' e JOIN ' . ExampleJoinedEntity::class . ' j',
                ForceIndexWalker::HINT_FORCE_INDEX_FOR_JOIN,
                ['example_joined_entity' => ExampleJoinedEntity::IDX_NAME],
                'SELECT e0_.name AS name_0 FROM example e0_ INNER JOIN example_joined_entity e1_ FORCE INDEX (IDX_name)',
            ],
        ];
    }
}
