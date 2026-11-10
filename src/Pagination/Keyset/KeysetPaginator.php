<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Pagination\Keyset;

use AnzuSystems\CommonBundle\Doctrine\Walker\IndexHintWalker;
use AnzuSystems\CommonBundle\Pagination\Keyset\Exception\InvalidCursorException;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use JsonException;
use LogicException;

final readonly class KeysetPaginator
{
    public const string UNSUPPORTED_ORDER_FIELD_MESSAGE = 'The keyset order field %s::%s must be a non-nullable field of the type %s, so that the cursor can hold its value and no row with null is left out.';

    public const string NOT_UNIQUE_LAST_ORDER_FIELD_MESSAGE = 'The last keyset order field %s::%s must be the single-column identifier or a field with #[ORM\\Column(unique: true)], because the cursor continues after its value, so rows with the same value on the boundary of a page would be left out.';

    private const string COLUMN_FORMAT = '%s.%s';
    private const string TYPE_SEPARATOR = ', ';
    private const string FINGERPRINT_ALGORITHM = 'xxh128';
    private const string EQUALITY_PARAMETER_PREFIX = 'keyset';
    private const string CURSOR_PARAMETER_PREFIX = 'cursor';
    private const string PLACEHOLDER_PREFIX = ':';

    public function __construct(
        private KeysetCursorCodec $cursorCodec,
        private KeysetIndexResolver $indexResolver,
    ) {
    }

    /**
     * A condition the caller adds to the query builder itself is not covered by the index check: check it with EXPLAIN.
     * The query builder must not join a to-many association: the limit counts SQL rows and Doctrine merges the rows of
     * one entity, so the page would come back short and without a next cursor. Filter by a collection with a subquery.
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
        $entityManager = $queryBuilder->getEntityManager();
        $metadata = $entityManager->getClassMetadata($entityClass);
        $alias = (string) array_first($queryBuilder->getRootAliases());
        $appliedEqualities = array_filter($equalities, static fn (mixed $value): bool => null !== $value);
        $fieldTypes = $this->resolveFieldTypes(metadata: $metadata, order: $order);
        $indexName = $this->indexResolver->resolve(
            entityManager: $entityManager,
            metadata: $metadata,
            equalityFields: array_keys($appliedEqualities),
            order: $order,
        );

        $this->addEqualities(queryBuilder: $queryBuilder, alias: $alias, equalities: $appliedEqualities);
        $queryBuilder->resetDQLPart('orderBy');
        foreach ($order->fields as $field) {
            $queryBuilder->addOrderBy($this->createColumn($alias, $field), $order->direction->value);
        }
        $queryFingerprint = $this->createQueryFingerprint($queryBuilder);
        if (is_string($cursor)) {
            $this->addCursorCondition(
                queryBuilder: $queryBuilder,
                alias: $alias,
                direction: $order->direction,
                values: $this->cursorCodec->decode(cursor: $cursor, fieldTypes: $fieldTypes, query: $queryFingerprint),
                fieldTypes: $fieldTypes,
            );
        }

        /** @var list<T> $records */
        $records = IndexHintWalker::hint($queryBuilder, [$alias => $indexName])
            ->setMaxResults($limit + 1)
            ->getQuery()
            ->getResult();
        $items = array_slice(array: $records, offset: 0, length: $limit);

        return new KeysetPage(
            items: new ArrayCollection($items),
            nextCursor: count($records) > $limit ? $this->encodeCursor(
                metadata: $metadata,
                fields: $order->fields,
                lastItem: array_last($items),
                queryFingerprint: $queryFingerprint,
            ) : null,
        );
    }

    /**
     * @param ClassMetadata<object> $metadata
     *
     * @return array<string, string>
     */
    private function resolveFieldTypes(ClassMetadata $metadata, KeysetOrder $order): array
    {
        $fieldTypes = [];
        foreach ($order->fields as $field) {
            $type = $metadata->getTypeOfField($field);
            if (null === $type || false === array_key_exists($type, KeysetCursorCodec::FIELD_TYPES) || $metadata->isNullable($field)) {
                throw new LogicException(sprintf(
                    self::UNSUPPORTED_ORDER_FIELD_MESSAGE,
                    $metadata->getName(),
                    $field,
                    implode(self::TYPE_SEPARATOR, array_keys(KeysetCursorCodec::FIELD_TYPES)),
                ));
            }
            $fieldTypes[$field] = $type;
        }
        $this->assertLastOrderFieldIsUnique($metadata, $order);

        return $fieldTypes;
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function assertLastOrderFieldIsUnique(ClassMetadata $metadata, KeysetOrder $order): void
    {
        $field = (string) array_last($order->fields);
        if ($metadata->isUniqueField($field) || ($metadata->isIdentifier($field) && 1 === count($metadata->getIdentifierColumnNames()))) {
            return;
        }

        throw new LogicException(sprintf(self::NOT_UNIQUE_LAST_ORDER_FIELD_MESSAGE, $metadata->getName(), $field));
    }

    /**
     * A cursor holds only the order values, so it is bound to the query without its cursor condition: a cursor of
     * another filter would continue at a row of that filter and skip rows of this one.
     */
    private function createQueryFingerprint(QueryBuilder $queryBuilder): string
    {
        $query = $queryBuilder->getQuery();

        return $query->getParameters()
            ->map(static fn (Parameter $parameter): array => [$parameter->getName(), $query->processParameterValue($parameter->getValue())])
            ->getValues()
            |> (static fn (array $parameters): string => serialize([$query->getDQL(), $parameters]))
            |> (static fn (string $data): string => hash(self::FINGERPRINT_ALGORITHM, $data));
    }

    /**
     * @param array<string, mixed> $equalities
     */
    private function addEqualities(QueryBuilder $queryBuilder, string $alias, array $equalities): void
    {
        $expr = $queryBuilder->expr();
        foreach (array_keys($equalities) as $position => $field) {
            $value = $equalities[$field];
            $parameter = self::EQUALITY_PARAMETER_PREFIX . $position;
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
        foreach (array_keys($values) as $position => $field) {
            $value = $values[$field];
            $column = $this->createColumn($alias, $field);
            $parameter = self::CURSOR_PARAMETER_PREFIX . $position;
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
    private function encodeCursor(ClassMetadata $metadata, array $fields, object $lastItem, string $queryFingerprint): string
    {
        return array_map(static fn (string $field): mixed => $metadata->getFieldValue($lastItem, $field), $fields)
            |> (static fn (array $values): array => array_combine($fields, $values))
            |> (fn (array $values): string => $this->cursorCodec->encode(values: $values, query: $queryFingerprint));
    }
}
