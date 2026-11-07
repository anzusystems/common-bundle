<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Doctrine\Walker;

use AnzuSystems\CommonBundle\Doctrine\Walker\ForceIndexWalker;
use AnzuSystems\CommonBundle\Tests\Data\Entity\Example;
use AnzuSystems\CommonBundle\Tests\Data\Entity\ExampleInheritanceChild;
use AnzuSystems\CommonBundle\Tests\Data\Entity\ExampleInheritanceParent;
use AnzuSystems\CommonBundle\Tests\Data\Entity\ExampleJoinedEntity;
use AnzuSystems\CommonBundle\Tests\Doctrine\AbstractDoctrineQueryTestCase;
use Doctrine\ORM\Query;
use LogicException;

final class ForceIndexWalkerQueryTest extends AbstractDoctrineQueryTestCase
{
    /**
     * @dataProvider dqlProvider
     *
     * @param string|array<string, string> $index
     */
    public function testIndexIsForcedByAnOptimizerHint(string $dql, string $type, string|array $index, string $expectedSql): void
    {
        self::assertSame($expectedSql, $this->createQuery(dql: $dql, type: $type, index: $index)->getSQL());
    }

    /**
     * @return array<string, array{string, string, string|array<string, string>, string}>
     */
    public static function dqlProvider(): array
    {
        return [
            'from' => [
                'SELECT e.name FROM ' . Example::class . ' e',
                ForceIndexWalker::HINT_FORCE_INDEX_FOR_FROM,
                Example::IDX_NAME_CREATED_AT,
                'SELECT /*+ INDEX(e0_ ' . Example::IDX_NAME_CREATED_AT . ') */ e0_.name AS name_0 FROM example e0_',
            ],
            'from with a condition' => [
                'SELECT e.name FROM ' . Example::class . ' e WHERE e.id > 0',
                ForceIndexWalker::HINT_FORCE_INDEX_FOR_FROM,
                Example::IDX_NAME_CREATED_AT,
                'SELECT /*+ INDEX(e0_ ' . Example::IDX_NAME_CREATED_AT . ') */ e0_.name AS name_0 FROM example e0_ WHERE e0_.id > 0',
            ],
            'from with distinct' => [
                'SELECT DISTINCT e.name FROM ' . Example::class . ' e',
                ForceIndexWalker::HINT_FORCE_INDEX_FOR_FROM,
                Example::IDX_NAME_CREATED_AT,
                'SELECT /*+ INDEX(e0_ ' . Example::IDX_NAME_CREATED_AT . ') */ DISTINCT e0_.name AS name_0 FROM example e0_',
            ],
            'join' => [
                'SELECT e.name FROM ' . Example::class . ' e JOIN ' . ExampleJoinedEntity::class . ' j',
                ForceIndexWalker::HINT_FORCE_INDEX_FOR_JOIN,
                ['example_joined_entity' => ExampleJoinedEntity::IDX_NAME],
                'SELECT /*+ INDEX(e1_ ' . ExampleJoinedEntity::IDX_NAME . ') */ e0_.name AS name_0 FROM example e0_ INNER JOIN example_joined_entity e1_',
            ],
            'join hint for the from table' => [
                'SELECT e.name FROM ' . Example::class . ' e JOIN ' . ExampleJoinedEntity::class . ' j',
                ForceIndexWalker::HINT_FORCE_INDEX_FOR_JOIN,
                ['example' => Example::IDX_NAME_CREATED_AT],
                'SELECT e0_.name AS name_0 FROM example e0_ INNER JOIN example_joined_entity e1_',
            ],
        ];
    }

    public function testParentTableOfClassTableInheritanceTakesTheJoinHint(): void
    {
        $sql = $this->createQuery(
            dql: 'SELECT c.title FROM ' . ExampleInheritanceChild::class . ' c',
            type: ForceIndexWalker::HINT_FORCE_INDEX_FOR_JOIN,
            index: ['example_inheritance_parent' => ExampleInheritanceParent::IDX_PARENT_NAME],
        )->getSQL();

        self::assertStringStartsWith('SELECT /*+ INDEX(e1_ ' . ExampleInheritanceParent::IDX_PARENT_NAME . ') */ ', (string) $sql);
        self::assertStringContainsString(' INNER JOIN example_inheritance_parent e1_ ', (string) $sql);
    }

    public function testUnmappedIndexFails(): void
    {
        $this->expectExceptionObject(new LogicException(sprintf(
            ForceIndexWalker::UNKNOWN_INDEX_MESSAGE,
            ExampleJoinedEntity::IDX_NAME,
            'example',
            Example::class,
        )));

        $this->createQuery(
            dql: 'SELECT e.name FROM ' . Example::class . ' e',
            type: ForceIndexWalker::HINT_FORCE_INDEX_FOR_FROM,
            index: ExampleJoinedEntity::IDX_NAME,
        )->getSQL();
    }

    /**
     * @param string|array<string, string> $index
     */
    private function createQuery(string $dql, string $type, string|array $index): Query
    {
        return $this->query
            ->setDQL($dql)
            ->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, ForceIndexWalker::class)
            ->setHint($type, $index);
    }
}
