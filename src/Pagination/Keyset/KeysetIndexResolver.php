<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Pagination\Keyset;

use AnzuSystems\CommonBundle\Doctrine\Walker\IndexHintWalker;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use LogicException;

/**
 * The last keyset field may be missing from the index when it is the single-column primary key: InnoDB appends the
 * primary key to every secondary index.
 */
final readonly class KeysetIndexResolver
{
    public const string MISSING_INDEX_MESSAGE = 'No named index of %s starts with the equality fields [%s] in any order followed by the order fields [%s], so the keyset query would sort its rows in a filesort. Add #[ORM\\Index(name: \'%s\', fields: [\'%s\'])].';

    private const string INDEX_NAME_PREFIX = 'IDX_';
    private const string NAME_SEPARATOR = '_';
    private const string LIST_SEPARATOR = ', ';
    private const string FIELD_LIST_SEPARATOR = '\', \'';

    /**
     * @param ClassMetadata<object> $metadata
     * @param list<string> $equalityFields
     *
     * @return string the name of the index to force
     */
    public function resolve(EntityManagerInterface $entityManager, ClassMetadata $metadata, array $equalityFields, KeysetOrder $order): string
    {
        $equalityColumns = array_map(fn (string $field): string => $this->resolveColumn($metadata, $field), $equalityFields);
        $orderColumns = array_map(fn (string $field): string => $this->resolveColumn($metadata, $field), $order->fields);
        $fieldTables = array_map(
            fn (string $field): ?string => $this->resolveFieldTable(entityManager: $entityManager, metadata: $metadata, field: $field),
            [...$equalityFields, ...$order->fields],
        );
        $primaryKeyColumn = $this->resolvePrimaryKeyColumn($metadata);

        foreach (IndexHintWalker::resolveMappedIndexes($entityManager, $metadata) as $indexName => $index) {
            if ($this->isTableOfEveryField($index['table'], $fieldTables) && $this->isKeysetIndex(
                indexColumns: $index['columns'],
                equalityColumns: $equalityColumns,
                orderColumns: $orderColumns,
                primaryKeyColumn: $primaryKeyColumn,
            )) {
                return $indexName;
            }
        }

        throw new LogicException($this->createMissingIndexMessage(
            metadata: $metadata,
            equalityFields: $equalityFields,
            order: $order,
            primaryKeyColumn: $primaryKeyColumn,
        ));
    }

    /**
     * @param ClassMetadata<object> $tableMetadata
     * @param list<?string> $fieldTables
     */
    private function isTableOfEveryField(ClassMetadata $tableMetadata, array $fieldTables): bool
    {
        $tableName = $tableMetadata->getTableName();

        return array_all($fieldTables, static fn (?string $fieldTable): bool => null === $fieldTable || $fieldTable === $tableName);
    }

    /**
     * @param list<string> $indexColumns
     * @param list<string> $equalityColumns
     * @param non-empty-list<string> $orderColumns
     */
    private function isKeysetIndex(array $indexColumns, array $equalityColumns, array $orderColumns, ?string $primaryKeyColumn): bool
    {
        $equalityCount = count($equalityColumns);
        $leadingColumns = array_slice(array: $indexColumns, offset: 0, length: $equalityCount);
        sort($leadingColumns);
        sort($equalityColumns);
        if ($leadingColumns !== $equalityColumns) {
            return false;
        }

        $trailingColumns = array_slice(array: $indexColumns, offset: $equalityCount);
        if (array_slice(array: $trailingColumns, offset: 0, length: count($orderColumns)) === $orderColumns) {
            return true;
        }

        return array_last($orderColumns) === $primaryKeyColumn
            && $trailingColumns === array_slice(array: $orderColumns, offset: 0, length: -1);
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function resolveColumn(ClassMetadata $metadata, string $field): string
    {
        if ($metadata->hasField($field)) {
            return $metadata->getColumnName($field);
        }
        if ($metadata->isSingleValuedAssociation($field)) {
            return $metadata->getSingleAssociationJoinColumnName($field);
        }

        throw new LogicException(sprintf('"%s" is neither a field nor a to-one association of %s.', $field, $metadata->getName()));
    }

    /**
     * @param ClassMetadata<object> $metadata
     *
     * @return string|null null for the primary key, which every table of class table inheritance holds
     */
    private function resolveFieldTable(EntityManagerInterface $entityManager, ClassMetadata $metadata, string $field): ?string
    {
        if ($metadata->isIdentifier($field)) {
            return null;
        }
        $owningClass = ($metadata->fieldMappings[$field] ?? $metadata->associationMappings[$field])->inherited ?? $metadata->getName();

        return $entityManager->getClassMetadata($owningClass)
            ->getTableName();
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function resolvePrimaryKeyColumn(ClassMetadata $metadata): ?string
    {
        $columns = $metadata->getIdentifierColumnNames();

        return 1 === count($columns) ? array_first($columns) : null;
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param list<string> $equalityFields
     */
    private function createMissingIndexMessage(ClassMetadata $metadata, array $equalityFields, KeysetOrder $order, ?string $primaryKeyColumn): string
    {
        $indexFields = [...$equalityFields, ...$order->fields];
        if ($this->resolveColumn($metadata, (string) array_last($order->fields)) === $primaryKeyColumn) {
            array_pop($indexFields);
        }

        return sprintf(
            self::MISSING_INDEX_MESSAGE,
            $metadata->getName(),
            implode(self::LIST_SEPARATOR, $equalityFields),
            implode(self::LIST_SEPARATOR, $order->fields),
            self::INDEX_NAME_PREFIX . implode(self::NAME_SEPARATOR, $indexFields),
            implode(self::FIELD_LIST_SEPARATOR, $indexFields),
        );
    }
}
