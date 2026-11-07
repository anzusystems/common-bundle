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
    public const string FIELDS_ON_SEVERAL_TABLES_MESSAGE = 'The equality and order fields [%s] of %s are columns of the tables [%s] of class table inheritance, and no index spans more than one table, so the keyset query would sort its rows in a filesort. Filter and order by fields of one table.';
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
        $equalityColumns = array_map(static fn (string $field): string => IndexHintWalker::resolveIndexColumn($metadata, $field), $equalityFields);
        $orderColumns = array_map(static fn (string $field): string => IndexHintWalker::resolveIndexColumn($metadata, $field), $order->fields);
        $fieldMetadata = $this->resolveFieldMetadata(entityManager: $entityManager, metadata: $metadata, fields: [...$equalityFields, ...$order->fields]);
        $primaryKeyColumn = $this->resolvePrimaryKeyColumn($metadata);

        foreach (IndexHintWalker::resolveMappedIndexes($entityManager, $metadata) as $indexName => $mappedIndexes) {
            foreach ($mappedIndexes as $index) {
                if ($index->tableMetadata->getTableName() === $fieldMetadata->getTableName() && $this->isKeysetIndex(
                    indexColumns: $index->columns,
                    equalityColumns: $equalityColumns,
                    orderColumns: $orderColumns,
                    primaryKeyColumn: $primaryKeyColumn,
                )) {
                    IndexHintWalker::resolveUnambiguousIndex(metadata: $metadata, index: $indexName, mappedIndexes: $mappedIndexes);

                    return $indexName;
                }
            }
        }

        throw new LogicException($this->createMissingIndexMessage(
            metadata: $fieldMetadata,
            equalityFields: $equalityFields,
            order: $order,
            primaryKeyColumn: $primaryKeyColumn,
        ));
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
     * Doctrine reads a field, the primary key too, from the table of the class that declares it, and no index spans two
     * tables of class table inheritance.
     *
     * @param ClassMetadata<object> $metadata
     * @param non-empty-list<string> $fields
     *
     * @return ClassMetadata<object> the metadata of the class whose table holds every field
     */
    private function resolveFieldMetadata(EntityManagerInterface $entityManager, ClassMetadata $metadata, array $fields): ClassMetadata
    {
        $fieldMetadata = [];
        foreach ($fields as $field) {
            $declaringMetadata = $entityManager->getClassMetadata(
                ($metadata->fieldMappings[$field] ?? $metadata->associationMappings[$field])->inherited ?? $metadata->getName(),
            );
            $fieldMetadata[$declaringMetadata->getTableName()] = $declaringMetadata;
        }
        if (1 < count($fieldMetadata)) {
            throw new LogicException(sprintf(
                self::FIELDS_ON_SEVERAL_TABLES_MESSAGE,
                implode(self::LIST_SEPARATOR, $fields),
                $metadata->getName(),
                implode(self::LIST_SEPARATOR, array_keys($fieldMetadata)),
            ));
        }

        return array_first($fieldMetadata);
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
        if (IndexHintWalker::resolveIndexColumn($metadata, (string) array_last($order->fields)) === $primaryKeyColumn) {
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
