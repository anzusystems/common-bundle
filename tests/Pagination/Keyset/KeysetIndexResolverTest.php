<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Pagination\Keyset;

use AnzuSystems\CommonBundle\Doctrine\Walker\IndexHintWalker;
use AnzuSystems\CommonBundle\Pagination\Keyset\KeysetIndexResolver;
use AnzuSystems\CommonBundle\Pagination\Keyset\KeysetOrder;
use AnzuSystems\CommonBundle\Tests\AnzuKernelTestCase;
use AnzuSystems\CommonBundle\Tests\Data\Entity\Example;
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
            'field of the child table of class table inheritance' => [ExampleInheritanceChild::class, [], ['title', 'id'], ExampleInheritanceChild::IDX_CHILD_TITLE],
        ];
    }

    /**
     * @dataProvider missingIndexProvider
     *
     * @param list<string> $equalityFields
     * @param non-empty-list<string> $orderFields
     */
    public function testQueryWithoutItsIndexIsALogicException(
        array $equalityFields,
        array $orderFields,
        string $expectedIndexName,
        string $expectedIndexFields,
    ): void {
        $this->expectExceptionObject(new LogicException(sprintf(
            KeysetIndexResolver::MISSING_INDEX_MESSAGE,
            Example::class,
            implode(', ', $equalityFields),
            implode(', ', $orderFields),
            $expectedIndexName,
            $expectedIndexFields,
        )));

        $this->resolve(entityClass: Example::class, equalityFields: $equalityFields, orderFields: $orderFields);
    }

    /**
     * @return array<string, array{list<string>, non-empty-list<string>, string, string}>
     */
    public static function missingIndexProvider(): array
    {
        return [
            'equality that no index starts with' => [['dummyEnum'], ['createdAt', 'id'], 'IDX_dummyEnum_createdAt', "dummyEnum', 'createdAt"],
            'another column before the appended primary key' => [[], ['name', 'id'], 'IDX_name', 'name'],
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
