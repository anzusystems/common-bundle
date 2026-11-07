<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Pagination\Keyset;

use AnzuSystems\CommonBundle\Doctrine\Walker\ForceIndexWalker;
use AnzuSystems\CommonBundle\Pagination\Keyset\KeysetCursorCodec;
use AnzuSystems\CommonBundle\Pagination\Keyset\KeysetIndexResolver;
use AnzuSystems\CommonBundle\Pagination\Keyset\KeysetOrder;
use AnzuSystems\CommonBundle\Pagination\Keyset\KeysetPage;
use AnzuSystems\CommonBundle\Pagination\Keyset\KeysetPaginator;
use AnzuSystems\CommonBundle\Tests\AnzuKernelTestCase;
use AnzuSystems\CommonBundle\Tests\Data\Entity\Example;
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
    private const int CURSOR_ID = 900_003;
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
            equalities: ['dummyEnum' => DummyEnum::StateTwo, 'name' => self::NAME],
            cursor: $this->createCursor(),
            limit: 1,
        );

        self::assertSame(
            sprintf(
                'SELECT example FROM %s example WHERE example.dummyEnum = :keysetDummyEnum AND example.name = :keysetName AND (example.createdAt %2$s :cursorCreatedAt OR (example.createdAt = :cursorCreatedAt AND example.id %2$s :cursorId)) ORDER BY example.createdAt %3$s, example.id %3$s',
                Example::class,
                $operator,
                $direction->value,
            ),
            $queryBuilder->getDQL(),
        );
        self::assertSame(DummyEnum::StateTwo, $queryBuilder->getParameter('keysetDummyEnum')?->getValue());
        self::assertSame(self::NAME, $queryBuilder->getParameter('keysetName')?->getValue());
        $createdAt = $queryBuilder->getParameter('cursorCreatedAt');
        self::assertSame(Types::DATETIME_IMMUTABLE, $createdAt?->getType());
        self::assertEquals(new DateTimeImmutable(self::CURSOR_CREATED_AT), $createdAt->getValue());
        $id = $queryBuilder->getParameter('cursorId');
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
            cursor: $this->createCursor(),
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
                ->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, ForceIndexWalker::class),
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
        return $page->items->map(static fn (Example $example): int => $example->getId())
            ->getValues();
    }

    private function createCursor(): string
    {
        return new KeysetCursorCodec()
            ->encode([
                'createdAt' => new DateTimeImmutable(self::CURSOR_CREATED_AT),
                'id' => self::CURSOR_ID,
            ]);
    }

    private function createQueryBuilder(): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('example')
            ->from(Example::class, 'example');
    }
}
