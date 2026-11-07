<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Pagination\Keyset;

use AnzuSystems\CommonBundle\Doctrine\Walker\ForceIndexWalker;
use AnzuSystems\CommonBundle\Pagination\Keyset\Exception\InvalidCursorException;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use JsonException;
use LogicException;

/**
 * Pages a query by a keyset instead of an offset, so a later page does not read and skip the rows of the earlier ones.
 */
final readonly class KeysetPaginator
{
    private const string COLUMN_FORMAT = '%s.%s';
    private const string EQUALITY_PARAMETER_PREFIX = 'keyset';
    private const string CURSOR_PARAMETER_PREFIX = 'cursor';
    private const string PLACEHOLDER_PREFIX = ':';

    public function __construct(
        private KeysetCursorCodec $cursorCodec,
        private KeysetIndexResolver $indexResolver,
    ) {
    }

    /**
     * Filters the query by the equalities, orders it by the keyset, continues after the row the cursor points to and
     * forces the index that KeysetIndexResolver finds for the equalities and the order, or throws a LogicException
     * without one. A condition the caller adds to the query builder itself is not covered by that index check: check
     * such a query with EXPLAIN.
     *
     * @template T of object
     *
     * @param class-string<T> $entityClass the root entity of the query builder
     * @param array<string, mixed> $equalities field or to-one association of the root entity => value; a null value
     *                                         adds no condition
     *
     * @return KeysetPage<T>
     *
     * @throws InvalidCursorException
     * @throws JsonException
     */
    public function paginate(
        QueryBuilder $queryBuilder,
        string $entityClass,
        KeysetOrder $order,
        array $equalities,
        ?string $cursor,
        int $limit,
    ): KeysetPage {
        if ($queryBuilder->hasHint(Query::HINT_CUSTOM_OUTPUT_WALKER)) {
            throw new LogicException('The keyset paginator forces its index through its own output walker, so the query builder must not set another one.');
        }
        $metadata = $queryBuilder->getEntityManager()
            ->getClassMetadata($entityClass);
        $alias = (string) array_first($queryBuilder->getRootAliases());
        $appliedEqualities = array_filter($equalities, static fn (mixed $value): bool => null !== $value);
        $indexName = $this->indexResolver->resolve(
            metadata: $metadata,
            equalityFields: array_keys($appliedEqualities),
            order: $order,
        );
        $fieldTypes = array_combine(
            $order->fields,
            array_map(static fn (string $field): string => (string) $metadata->getTypeOfField($field), $order->fields),
        );

        $this->addEqualities(queryBuilder: $queryBuilder, alias: $alias, equalities: $appliedEqualities);
        $queryBuilder->resetDQLPart('orderBy');
        foreach ($order->fields as $field) {
            $queryBuilder->addOrderBy($this->createColumn($alias, $field), $order->direction->value);
        }
        if (is_string($cursor)) {
            $this->addCursorCondition(
                queryBuilder: $queryBuilder,
                alias: $alias,
                direction: $order->direction,
                values: $this->cursorCodec->decode($cursor, $fieldTypes),
                fieldTypes: $fieldTypes,
            );
        }

        /** @var list<T> $records */
        $records = $queryBuilder
            ->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, ForceIndexWalker::class)
            ->setHint(ForceIndexWalker::HINT_FORCE_INDEX_FOR_FROM, $indexName)
            ->setMaxResults($limit + 1)
            ->getQuery()
            ->getResult();
        $items = array_slice(array: $records, offset: 0, length: $limit);

        return new KeysetPage(
            items: new ArrayCollection($items),
            nextCursor: count($records) > $limit ? $this->encodeCursor(metadata: $metadata, fields: $order->fields, lastItem: array_last($items)) : null,
        );
    }

    /**
     * @param array<string, mixed> $equalities
     */
    private function addEqualities(QueryBuilder $queryBuilder, string $alias, array $equalities): void
    {
        $expr = $queryBuilder->expr();
        foreach ($equalities as $field => $value) {
            $parameter = self::EQUALITY_PARAMETER_PREFIX . ucfirst($field);
            $queryBuilder
                ->andWhere($expr->eq($this->createColumn($alias, $field), self::PLACEHOLDER_PREFIX . $parameter))
                ->setParameter($parameter, $value);
        }
    }

    /**
     * @param array<string, DateTimeImmutable|int|string> $values
     * @param array<string, string> $fieldTypes
     */
    private function addCursorCondition(
        QueryBuilder $queryBuilder,
        string $alias,
        Order $direction,
        array $values,
        array $fieldTypes,
    ): void {
        $expr = $queryBuilder->expr();
        $equalities = [];
        $alternatives = [];
        foreach ($values as $field => $value) {
            $column = $this->createColumn($alias, $field);
            $parameter = self::CURSOR_PARAMETER_PREFIX . ucfirst($field);
            $placeholder = self::PLACEHOLDER_PREFIX . $parameter;
            $comparison = match ($direction) {
                Order::Ascending => $expr->gt($column, $placeholder),
                Order::Descending => $expr->lt($column, $placeholder),
            };
            $alternatives[] = $expr->andX(...[...$equalities, $comparison]);
            $equalities[] = $expr->eq($column, $placeholder);
            $queryBuilder->setParameter(key: $parameter, value: $value, type: $fieldTypes[$field]);
        }
        $queryBuilder->andWhere($expr->orX(...$alternatives));
    }

    private function createColumn(string $alias, string $field): string
    {
        return sprintf(self::COLUMN_FORMAT, $alias, $field);
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param non-empty-list<string> $fields
     *
     * @throws JsonException
     */
    private function encodeCursor(ClassMetadata $metadata, array $fields, object $lastItem): string
    {
        return array_map(static fn (string $field): mixed => $metadata->getFieldValue($lastItem, $field), $fields)
            |> (static fn (array $values): array => array_combine($fields, $values))
            |> $this->cursorCodec->encode(...);
    }
}
