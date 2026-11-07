<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Doctrine\Walker;

use AnzuSystems\CommonBundle\Doctrine\Walker\IndexHintWalker;
use AnzuSystems\CommonBundle\Tests\Data\Entity\Example;
use AnzuSystems\CommonBundle\Tests\Data\Entity\ExampleInheritanceChild;
use AnzuSystems\CommonBundle\Tests\Data\Entity\ExampleInheritanceParent;
use AnzuSystems\CommonBundle\Tests\Data\Entity\ExampleJoinedEntity;
use AnzuSystems\CommonBundle\Tests\Doctrine\AbstractDoctrineQueryTestCase;
use LogicException;

final class IndexHintWalkerQueryTest extends AbstractDoctrineQueryTestCase
{
    /**
     * @dataProvider dqlProvider
     *
     * @param non-empty-array<string, string> $indexByAlias
     */
    public function testIndexIsForcedByAnOptimizerHint(string $dql, array $indexByAlias, string $expectedSql): void
    {
        self::assertSame($expectedSql, $this->getSql(dql: $dql, indexByAlias: $indexByAlias));
    }

    /**
     * @return array<string, array{string, non-empty-array<string, string>, string}>
     */
    public static function dqlProvider(): array
    {
        return [
            'from' => [
                'SELECT e.name FROM ' . Example::class . ' e',
                ['e' => Example::IDX_NAME_CREATED_AT],
                'SELECT /*+ INDEX(e0_ ' . Example::IDX_NAME_CREATED_AT . ') */ e0_.name AS name_0 FROM example e0_',
            ],
            'from with a condition' => [
                'SELECT e.name FROM ' . Example::class . ' e WHERE e.id > 0',
                ['e' => Example::IDX_NAME_CREATED_AT],
                'SELECT /*+ INDEX(e0_ ' . Example::IDX_NAME_CREATED_AT . ') */ e0_.name AS name_0 FROM example e0_ WHERE e0_.id > 0',
            ],
            'from with distinct' => [
                'SELECT DISTINCT e.name FROM ' . Example::class . ' e',
                ['e' => Example::IDX_NAME_CREATED_AT],
                'SELECT /*+ INDEX(e0_ ' . Example::IDX_NAME_CREATED_AT . ') */ DISTINCT e0_.name AS name_0 FROM example e0_',
            ],
            'primary key' => [
                'SELECT e.name FROM ' . Example::class . ' e',
                ['e' => IndexHintWalker::PRIMARY_INDEX],
                'SELECT /*+ INDEX(e0_ ' . IndexHintWalker::PRIMARY_INDEX . ') */ e0_.name AS name_0 FROM example e0_',
            ],
            'join' => [
                'SELECT e.name FROM ' . Example::class . ' e JOIN ' . ExampleJoinedEntity::class . ' j',
                ['j' => ExampleJoinedEntity::IDX_NAME],
                'SELECT /*+ INDEX(e1_ ' . ExampleJoinedEntity::IDX_NAME . ') */ e0_.name AS name_0 FROM example e0_ INNER JOIN example_joined_entity e1_',
            ],
            'from and join' => [
                'SELECT e.name FROM ' . Example::class . ' e JOIN ' . ExampleJoinedEntity::class . ' j',
                ['e' => Example::IDX_NAME_CREATED_AT, 'j' => ExampleJoinedEntity::IDX_NAME],
                'SELECT /*+ INDEX(e0_ ' . Example::IDX_NAME_CREATED_AT . ') INDEX(e1_ ' . ExampleJoinedEntity::IDX_NAME . ') */ e0_.name AS name_0 FROM example e0_ INNER JOIN example_joined_entity e1_',
            ],
        ];
    }

    public function testIndexOfTheParentTableOfClassTableInheritanceTargetsTheParentTable(): void
    {
        $sql = $this->getSql(
            dql: 'SELECT c.title FROM ' . ExampleInheritanceChild::class . ' c',
            indexByAlias: ['c' => ExampleInheritanceParent::IDX_PARENT_NAME],
        );

        self::assertStringStartsWith('SELECT /*+ INDEX(e1_ ' . ExampleInheritanceParent::IDX_PARENT_NAME . ') */ ', $sql);
        self::assertStringContainsString(' INNER JOIN example_inheritance_parent e1_ ', $sql);
    }

    public function testIndexOfTheChildTableOfClassTableInheritanceTargetsTheChildTable(): void
    {
        $sql = $this->getSql(
            dql: 'SELECT c.title FROM ' . ExampleInheritanceChild::class . ' c',
            indexByAlias: ['c' => ExampleInheritanceChild::IDX_CHILD_TITLE],
        );

        self::assertStringStartsWith('SELECT /*+ INDEX(e0_ ' . ExampleInheritanceChild::IDX_CHILD_TITLE . ') */ ', $sql);
    }

    /**
     * @dataProvider unmappedIndexProvider
     */
    public function testUnmappedIndexFails(string $dql, string $alias, string $entityClass): void
    {
        $this->expectExceptionObject(new LogicException(sprintf(IndexHintWalker::UNKNOWN_INDEX_MESSAGE, 'IDX_unknown', $entityClass)));

        $this->getSql(dql: $dql, indexByAlias: [$alias => 'IDX_unknown']);
    }

    /**
     * @return array<string, array{string, string, class-string}>
     */
    public static function unmappedIndexProvider(): array
    {
        $joinDql = 'SELECT e.name FROM ' . Example::class . ' e JOIN ' . ExampleJoinedEntity::class . ' j';

        return [
            'from' => [$joinDql, 'e', Example::class],
            'join' => [$joinDql, 'j', ExampleJoinedEntity::class],
        ];
    }

    public function testUnknownAliasFails(): void
    {
        $this->expectExceptionObject(new LogicException(sprintf(IndexHintWalker::UNKNOWN_ALIAS_MESSAGE, 'x', Example::IDX_NAME_CREATED_AT)));

        $this->getSql(dql: 'SELECT e.name FROM ' . Example::class . ' e', indexByAlias: ['x' => Example::IDX_NAME_CREATED_AT]);
    }

    /**
     * @param non-empty-array<string, string> $indexByAlias
     */
    private function getSql(string $dql, array $indexByAlias): string
    {
        return (string) IndexHintWalker::hint($this->query->setDQL($dql), $indexByAlias)->getSQL();
    }
}
