<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Pagination\Keyset;

use AnzuSystems\CommonBundle\Doctrine\Walker\IndexHintWalker;
use AnzuSystems\CommonBundle\Pagination\Keyset\KeysetIndexResolver;
use AnzuSystems\CommonBundle\Pagination\Keyset\KeysetOrder;
use AnzuSystems\CommonBundle\Tests\AnzuKernelTestCase;
use AnzuSystems\CommonBundle\Tests\Data\Entity\Example;
use AnzuSystems\CommonBundle\Tests\Data\Entity\ExampleAmbiguousIndexParent;
use AnzuSystems\CommonBundle\Tests\Data\Entity\ExampleAmbiguousIndexSecondChild;
use AnzuSystems\CommonBundle\Tests\Data\Entity\ExampleInheritanceChild;
use AnzuSystems\CommonBundle\Tests\Data\Entity\ExampleInheritanceParent;
use AnzuSystems\CommonBundle\Tests\Data\Entity\ExampleJoinedEntity;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;

final class KeysetIndexResolverTest extends AnzuKernelTestCase
{
    /**
     * @dataProvider indexProvider
     *
     * @param class-string $entityClass
     * @param list<string> $equalityFields
     * @param non-empty-list<string> $orderFields
     */
    public function testIndexStartsWithTheEqualitiesFollowedByTheOrder(
        string $entityClass,
        array $equalityFields,
        array $orderFields,
        string $expectedIndex,
    ): void {
        self::assertSame($expectedIndex, $this->resolve(entityClass: $entityClass, equalityFields: $equalityFields, orderFields: $orderFields));
    }

    /**
     * @return array<string, array{class-string, list<string>, non-empty-list<string>, string}>
     */
    public static function indexProvider(): array
    {
        return [
            'primary key appended by InnoDB' => [Example::class, ['name'], ['createdAt', 'id'], Example::IDX_NAME_CREATED_AT],
            'equalities in another order than in the index' => [Example::class, ['dummyEnum', 'name'], ['createdAt', 'id'], Example::IDX_NAME_DUMMY_ENUM_CREATED_AT],
            'primary key alone' => [Example::class, [], ['id'], IndexHintWalker::PRIMARY_INDEX],
            'equality on an association' => [ExampleJoinedEntity::class, ['joinedEntity'], ['id'], ExampleJoinedEntity::IDX_JOINED_ENTITY],
            'order without equalities' => [ExampleJoinedEntity::class, [], ['name', 'id'], ExampleJoinedEntity::IDX_NAME],
            'index by columns' => [ExampleJoinedEntity::class, ['joinedEntity'], ['name', 'id'], ExampleJoinedEntity::IDX_JOINED_ENTITY_NAME],
            'unique constraint' => [ExampleJoinedEntity::class, ['name'], ['joinedEntity', 'id'], ExampleJoinedEntity::UNIQ_NAME_JOINED_ENTITY],
            'field of the parent table of class table inheritance' => [ExampleInheritanceChild::class, [], ['name', 'id'], ExampleInheritanceParent::IDX_PARENT_NAME],
            'field of the child table of class table inheritance' => [ExampleInheritanceChild::class, [], ['title'], ExampleInheritanceChild::IDX_CHILD_TITLE],
            'primary key of class table inheritance' => [ExampleInheritanceChild::class, [], ['id'], IndexHintWalker::PRIMARY_INDEX],
            'primary key beside an index name on two tables' => [ExampleAmbiguousIndexSecondChild::class, [], ['id'], IndexHintWalker::PRIMARY_INDEX],
            'primary key of a root whose children share an index name' => [ExampleAmbiguousIndexParent::class, [], ['id'], IndexHintWalker::PRIMARY_INDEX],
        ];
    }

    /**
     * @dataProvider missingIndexProvider
     *
     * @param class-string $entityClass
     * @param list<string> $equalityFields
     * @param non-empty-list<string> $orderFields
     * @param class-string $expectedIndexClass the class whose table holds the fields
     */
    public function testQueryWithoutItsIndexIsALogicException(
        string $entityClass,
        array $equalityFields,
        array $orderFields,
        string $expectedIndexClass,
        string $expectedIndexName,
        string $expectedIndexFields,
    ): void {
        $this->expectExceptionObject(new LogicException(sprintf(
            KeysetIndexResolver::MISSING_INDEX_MESSAGE,
            $expectedIndexClass,
            implode(', ', $equalityFields),
            implode(', ', $orderFields),
            $expectedIndexName,
            $expectedIndexFields,
        )));

        $this->resolve(entityClass: $entityClass, equalityFields: $equalityFields, orderFields: $orderFields);
    }

    /**
     * @return array<string, array{class-string, list<string>, non-empty-list<string>, class-string, string, string}>
     */
    public static function missingIndexProvider(): array
    {
        return [
            'equality that no index starts with' => [Example::class, ['dummyEnum'], ['createdAt', 'id'], Example::class, 'IDX_dummyEnum_createdAt', "dummyEnum', 'createdAt"],
            'another column before the appended primary key' => [Example::class, [], ['name', 'id'], Example::class, 'IDX_name', 'name'],
            'fields of the parent table of class table inheritance' => [
                ExampleInheritanceChild::class,
                ['id'],
                ['name'],
                ExampleInheritanceParent::class,
                'IDX_id_name',
                "id', 'name",
            ],
        ];
    }

    public function testIndexNameOnTwoTablesOfClassTableInheritanceIsALogicException(): void
    {
        $this->expectExceptionObject(new LogicException(sprintf(
            IndexHintWalker::AMBIGUOUS_INDEX_MESSAGE,
            ExampleAmbiguousIndexParent::IDX_NAME,
            ExampleAmbiguousIndexSecondChild::class,
        )));

        $this->resolve(entityClass: ExampleAmbiguousIndexSecondChild::class, equalityFields: [], orderFields: ['name', 'id']);
    }

    /**
     * @dataProvider severalTablesProvider
     *
     * @param list<string> $equalityFields
     * @param non-empty-list<string> $orderFields
     */
    public function testFieldsOfSeveralTablesOfClassTableInheritanceAreALogicException(
        array $equalityFields,
        array $orderFields,
        string $expectedTables,
    ): void {
        $this->expectExceptionObject(new LogicException(sprintf(
            KeysetIndexResolver::FIELDS_ON_SEVERAL_TABLES_MESSAGE,
            implode(', ', [...$equalityFields, ...$orderFields]),
            ExampleInheritanceChild::class,
            $expectedTables,
        )));

        $this->resolve(entityClass: ExampleInheritanceChild::class, equalityFields: $equalityFields, orderFields: $orderFields);
    }

    /**
     * @return array<string, array{list<string>, non-empty-list<string>, string}>
     */
    public static function severalTablesProvider(): array
    {
        return [
            'child field ordered by the primary key of the parent table' => [[], ['title', 'id'], 'example_inheritance_child, example_inheritance_parent'],
            'child equality with a parent order' => [['title'], ['name'], 'example_inheritance_child, example_inheritance_parent'],
        ];
    }

    /**
     * @param class-string $entityClass
     * @param list<string> $equalityFields
     * @param non-empty-list<string> $orderFields
     */
    private function resolve(string $entityClass, array $equalityFields, array $orderFields): string
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        return new KeysetIndexResolver()
            ->resolve(
                entityManager: $entityManager,
                metadata: $entityManager->getClassMetadata($entityClass),
                equalityFields: $equalityFields,
                order: new KeysetOrder(fields: $orderFields, direction: Order::Descending),
            );
    }
}
