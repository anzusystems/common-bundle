<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Pagination\Keyset;

use AnzuSystems\CommonBundle\Doctrine\Walker\IndexHintWalker;
use AnzuSystems\CommonBundle\Helper\CollectionHelper;
use AnzuSystems\CommonBundle\Pagination\Keyset\Exception\InvalidCursorException;
use AnzuSystems\CommonBundle\Pagination\Keyset\KeysetCursorCodec;
use AnzuSystems\CommonBundle\Pagination\Keyset\KeysetIndexResolver;
use AnzuSystems\CommonBundle\Pagination\Keyset\KeysetOrder;
use AnzuSystems\CommonBundle\Pagination\Keyset\KeysetPage;
use AnzuSystems\CommonBundle\Pagination\Keyset\KeysetPaginator;
use AnzuSystems\CommonBundle\Tests\AnzuKernelTestCase;
use AnzuSystems\CommonBundle\Tests\Data\Entity\Example;
use AnzuSystems\CommonBundle\Tests\Data\Entity\ExampleInheritanceChild;
use AnzuSystems\CommonBundle\Tests\Data\Entity\ExampleInheritanceParent;
use AnzuSystems\CommonBundle\Tests\Data\Entity\ExampleJoinedEntity;
use AnzuSystems\CommonBundle\Tests\Data\Model\Enum\DummyEnum;
use DateTimeImmutable;
use Doctrine\Common\Collections\Order;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use LogicException;

final class KeysetPaginatorTest extends AnzuKernelTestCase
{
    private const string NAME = 'keyset';
    private const array ROWS = [
        900_001 => ['2026-01-01 10:00:00', DummyEnum::StateOne],
        900_002 => ['2026-01-02 10:00:00', DummyEnum::StateTwo],
        900_003 => ['2026-01-02 10:00:00', DummyEnum::StateOne],
        900_004 => ['2026-01-03 10:00:00', DummyEnum::StateTwo],
        900_005 => ['2026-01-02 10:00:00', DummyEnum::StateTwo],
    ];
    private const array FIELDS = ['createdAt', 'id'];
    private const int CURSOR_ID = 900_005;
    private const string CURSOR_CREATED_AT = '2026-01-02 10:00:00';

    private EntityManagerInterface $entityManager;
    private KeysetPaginator $paginator;

    protected function setUp(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;
        $this->paginator = new KeysetPaginator(cursorCodec: new KeysetCursorCodec(), indexResolver: new KeysetIndexResolver());

        $this->entityManager->beginTransaction();
        foreach (self::ROWS as $id => [$createdAt, $dummyEnum]) {
            $this->entityManager->persist(
                new Example()
                    ->setId($id)
                    ->setName(self::NAME)
                    ->setCreatedAt(new DateTimeImmutable($createdAt))
                    ->setDummyEnum($dummyEnum)
            );
        }
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    protected function tearDown(): void
    {
        $this->entityManager->rollback();
        $this->entityManager->clear();
    }

    /**
     * @dataProvider walkProvider
     *
     * @param list<list<int>> $expectedPages
     */
    public function testCursorWalksEveryPageInTheOrderOfTheKeyset(Order $direction, ?DummyEnum $dummyEnum, array $expectedPages): void
    {
        $page = $this->paginate(direction: $direction, dummyEnum: $dummyEnum, cursor: null, limit: 2);
        $pages = [$this->toIds($page)];
        while (is_string($page->nextCursor)) {
            $page = $this->paginate(direction: $direction, dummyEnum: $dummyEnum, cursor: $page->nextCursor, limit: 2);
            $pages[] = $this->toIds($page);
        }

        self::assertSame($expectedPages, $pages);
    }

    /**
     * @return array<string, array{Order, ?DummyEnum, list<list<int>>}>
     */
    public static function walkProvider(): array
    {
        return [
            'descending, the same date by id' => [Order::Descending, null, [[900_004, 900_005], [900_003, 900_002], [900_001]]],
            'ascending, the same date by id' => [Order::Ascending, null, [[900_001, 900_002], [900_003, 900_005], [900_004]]],
            'descending, filtered by an enum' => [Order::Descending, DummyEnum::StateTwo, [[900_004, 900_005], [900_002]]],
            'ascending, filtered by an enum' => [Order::Ascending, DummyEnum::StateTwo, [[900_002, 900_005], [900_004]]],
        ];
    }

    /**
     * @dataProvider conditionProvider
     */
    public function testCursorContinuesAfterItsRowWithAnOrCondition(Order $direction, string $operator): void
    {
        $queryBuilder = $this->createQueryBuilder();

        $this->paginator->paginate(
            queryBuilder: $queryBuilder,
            entityClass: Example::class,
            order: new KeysetOrder(fields: self::FIELDS, direction: $direction),
            equalities: ['name' => self::NAME, 'dummyEnum' => DummyEnum::StateTwo],
            cursor: $this->createCursor(direction: $direction, dummyEnum: DummyEnum::StateTwo),
            limit: 1,
        );

        self::assertSame(
            sprintf(
                'SELECT example FROM %s example WHERE example.name = :keyset0 AND example.dummyEnum = :keyset1 AND (example.createdAt %2$s :cursor0 OR (example.createdAt = :cursor0 AND example.id %2$s :cursor1)) ORDER BY example.createdAt %3$s, example.id %3$s',
                Example::class,
                $operator,
                $direction->value,
            ),
            $queryBuilder->getDQL(),
        );
        self::assertSame(self::NAME, $queryBuilder->getParameter('keyset0')?->getValue());
        self::assertSame(DummyEnum::StateTwo, $queryBuilder->getParameter('keyset1')?->getValue());
        $createdAt = $queryBuilder->getParameter('cursor0');
        self::assertSame(Types::DATETIME_IMMUTABLE, $createdAt?->getType());
        self::assertEquals(new DateTimeImmutable(self::CURSOR_CREATED_AT), $createdAt->getValue());
        $id = $queryBuilder->getParameter('cursor1');
        self::assertSame(Types::INTEGER, $id?->getType());
        self::assertSame(self::CURSOR_ID, $id->getValue());
        self::assertSame(2, $queryBuilder->getMaxResults());
    }

    /**
     * @return array<string, array{Order, string}>
     */
    public static function conditionProvider(): array
    {
        return [
            'descending' => [Order::Descending, '<'],
            'ascending' => [Order::Ascending, '>'],
        ];
    }

    /**
     * @dataProvider forcedIndexProvider
     */
    public function testQueryForcesTheIndexOfItsEqualitiesAfterTheRootTable(?DummyEnum $dummyEnum, string $expectedIndex): void
    {
        $queryBuilder = $this->createQueryBuilder();

        $this->paginator->paginate(
            queryBuilder: $queryBuilder,
            entityClass: Example::class,
            order: new KeysetOrder(fields: self::FIELDS, direction: Order::Descending),
            equalities: ['name' => self::NAME, 'dummyEnum' => $dummyEnum],
            cursor: $this->createCursor(direction: Order::Descending, dummyEnum: $dummyEnum),
            limit: 1,
        );

        self::assertStringContainsString(
            sprintf('SELECT /*+ INDEX(e0_ %s) */ ', $expectedIndex),
            $queryBuilder->getQuery()
                ->getSQL(),
        );
    }

    /**
     * @return array<string, array{?DummyEnum, string}>
     */
    public static function forcedIndexProvider(): array
    {
        return [
            'null equality adds no condition' => [null, Example::IDX_NAME_CREATED_AT],
            'every equality' => [DummyEnum::StateTwo, Example::IDX_NAME_DUMMY_ENUM_CREATED_AT],
        ];
    }

    /**
     * @dataProvider anotherQueryProvider
     */
    public function testCursorOfAnotherQueryIsAnInvalidCursorException(Order $direction, ?DummyEnum $dummyEnum): void
    {
        $cursor = $this->createCursor(direction: Order::Descending, dummyEnum: DummyEnum::StateTwo);

        $this->expectExceptionObject(new InvalidCursorException());

        $this->paginate(direction: $direction, dummyEnum: $dummyEnum, cursor: $cursor, limit: 2);
    }

    /**
     * @return array<string, array{Order, ?DummyEnum}>
     */
    public static function anotherQueryProvider(): array
    {
        return [
            'another equality value' => [Order::Descending, DummyEnum::StateOne],
            'equality left out' => [Order::Descending, null],
            'another direction' => [Order::Ascending, DummyEnum::StateTwo],
        ];
    }

    /**
     * @dataProvider unsupportedOrderFieldProvider
     *
     * @param class-string $entityClass
     * @param non-empty-list<string> $orderFields
     */
    public function testOrderFieldACursorCannotHoldIsALogicException(string $entityClass, array $orderFields, string $expectedField): void
    {
        $this->expectExceptionObject(new LogicException(sprintf(
            KeysetPaginator::UNSUPPORTED_ORDER_FIELD_MESSAGE,
            $entityClass,
            $expectedField,
            implode(', ', array_keys(KeysetCursorCodec::FIELD_TYPES)),
        )));

        $this->paginator->paginate(
            queryBuilder: $this->entityManager->createQueryBuilder()
                ->select('entity')
                ->from($entityClass, 'entity'),
            entityClass: $entityClass,
            order: new KeysetOrder(fields: $orderFields, direction: Order::Descending),
            equalities: [],
            cursor: null,
            limit: 1,
        );
    }

    /**
     * @return array<string, array{class-string, non-empty-list<string>, string}>
     */
    public static function unsupportedOrderFieldProvider(): array
    {
        return [
            'type the cursor cannot hold' => [Example::class, ['dummyEnum', 'id'], 'dummyEnum'],
            'nullable field' => [ExampleJoinedEntity::class, ['archivedAt', 'id'], 'archivedAt'],
            'association' => [ExampleJoinedEntity::class, ['joinedEntity', 'id'], 'joinedEntity'],
        ];
    }

    public function testKeysetOfAClassTableInheritanceChildForcesTheIndexOfTheParentTable(): void
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('child')
            ->from(ExampleInheritanceChild::class, 'child');

        $this->paginator->paginate(
            queryBuilder: $queryBuilder,
            entityClass: ExampleInheritanceChild::class,
            order: new KeysetOrder(fields: ['name', 'id'], direction: Order::Descending),
            equalities: [],
            cursor: null,
            limit: 1,
        );

        $sql = $queryBuilder->getQuery()
            ->getSQL();
        self::assertStringStartsWith(sprintf('SELECT /*+ INDEX(e0_ %s) */ ', ExampleInheritanceParent::IDX_PARENT_NAME), $sql);
        self::assertStringContainsString(' INNER JOIN example_inheritance_parent e0_ ', $sql);
    }

    public function testPageWithEveryRemainingRowHasNoNextCursor(): void
    {
        $page = $this->paginate(direction: Order::Descending, dummyEnum: null, cursor: null, limit: count(self::ROWS));

        self::assertCount(count(self::ROWS), $page->items);
        self::assertNull($page->nextCursor);
    }

    public function testQueryBuilderWithAnOutputWalkerIsALogicException(): void
    {
        $this->expectException(LogicException::class);

        $this->paginator->paginate(
            queryBuilder: $this->createQueryBuilder()
                ->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, IndexHintWalker::class),
            entityClass: Example::class,
            order: new KeysetOrder(fields: self::FIELDS, direction: Order::Descending),
            equalities: ['name' => self::NAME],
            cursor: null,
            limit: 1,
        );
    }

    /**
     * @return KeysetPage<Example>
     */
    private function paginate(Order $direction, ?DummyEnum $dummyEnum, ?string $cursor, int $limit): KeysetPage
    {
        return $this->paginator->paginate(
            queryBuilder: $this->createQueryBuilder(),
            entityClass: Example::class,
            order: new KeysetOrder(fields: self::FIELDS, direction: $direction),
            equalities: ['name' => self::NAME, 'dummyEnum' => $dummyEnum],
            cursor: $cursor,
            limit: $limit,
        );
    }

    /**
     * @param KeysetPage<Example> $page
     *
     * @return list<int>
     */
    private function toIds(KeysetPage $page): array
    {
        /** @var list<int> $ids */
        $ids = CollectionHelper::traversableToIds(
            $page->items,
            static fn (Example $example): int => $example->getId(),
        );

        return $ids;
    }

    private function createCursor(Order $direction, ?DummyEnum $dummyEnum): string
    {
        $cursor = $this->paginate(direction: $direction, dummyEnum: $dummyEnum, cursor: null, limit: 2)
            ->nextCursor;
        self::assertIsString($cursor);

        return $cursor;
    }

    private function createQueryBuilder(): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('example')
            ->from(Example::class, 'example');
    }
}
