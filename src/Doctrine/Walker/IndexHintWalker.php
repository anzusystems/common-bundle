<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Doctrine\Walker;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\AST\SelectStatement;
use Doctrine\ORM\Query\SqlOutputWalker;
use Doctrine\ORM\QueryBuilder;
use LogicException;

/**
 * Forces indexes with the MySQL index-level optimizer hint INDEX(), which supersedes FORCE INDEX (to be deprecated in a
 * future MySQL release). MySQL only warns about a hint naming an unknown index and ignores it, so every index name is
 * checked against the mapping and an unknown one fails instead of the query silently losing its plan.
 */
final class IndexHintWalker extends SqlOutputWalker
{
    public const string HINT_INDEX = 'IndexHintWalker.Index';
    public const string PRIMARY_INDEX = 'PRIMARY';
    public const string UNKNOWN_ALIAS_MESSAGE = 'The query has no entity alias "%s" to force the index "%s" on.';
    public const string UNKNOWN_INDEX_MESSAGE = 'The index "%s" is not mapped on any table of %s, so MySQL would ignore the hint forcing it.';

    private const string SELECT_KEYWORD = 'SELECT';
    private const string OPTIMIZER_HINT_FORMAT = 'SELECT /*+ %s */';
    private const string INDEX_HINT_FORMAT = 'INDEX(%s %s)';
    private const string HINT_SEPARATOR = ' ';

    /**
     * @template TQuery of Query|QueryBuilder
     *
     * @param TQuery $query
     * @param non-empty-array<string, string> $indexByAlias the index to force on the table of each DQL alias
     *
     * @return TQuery
     */
    public static function hint(Query|QueryBuilder $query, array $indexByAlias): Query|QueryBuilder
    {
        return $query
            ->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, self::class)
            ->setHint(self::HINT_INDEX, $indexByAlias);
    }

    /**
     * @param ClassMetadata<object> $metadata
     *
     * @return array<string, array{table: ClassMetadata<object>, columns: list<string>}> every index the alias of the
     *         entity can force by its name, with the metadata of the table that maps it; PRIMARY is on the entity's table
     */
    public static function resolveMappedIndexes(EntityManagerInterface $entityManager, ClassMetadata $metadata): array
    {
        $mappedIndexes = [
            self::PRIMARY_INDEX => [
                'table' => $metadata,
                'columns' => $metadata->getIdentifierColumnNames(),
            ],
        ];
        foreach (self::getTableMetadata($entityManager, $metadata) as $tableMetadata) {
            $indexes = [...$tableMetadata->table['indexes'] ?? [], ...$tableMetadata->table['uniqueConstraints'] ?? []];
            foreach ($indexes as $name => $index) {
                if (is_string($name)) {
                    $mappedIndexes[$name] ??= [
                        'table' => $tableMetadata,
                        'columns' => $index['columns'] ?? array_map(
                            static fn (string $field): string => self::resolveIndexColumn($tableMetadata, $field),
                            $index['fields'] ?? [],
                        ),
                    ];
                }
            }
        }

        return $mappedIndexes;
    }

    protected function createSqlForFinalizer(SelectStatement $selectStatement): string
    {
        $sql = parent::createSqlForFinalizer($selectStatement);
        $indexByAlias = $this->getQuery()
            ->getHint(self::HINT_INDEX);
        if (false === is_array($indexByAlias) || empty($indexByAlias)) {
            return $sql;
        }

        $indexHints = [];
        foreach ($indexByAlias as $dqlAlias => $index) {
            $indexHints[] = sprintf(
                self::INDEX_HINT_FORMAT,
                $this->resolveTableAlias(dqlAlias: (string) $dqlAlias, index: (string) $index),
                $index,
            );
        }

        return sprintf(self::OPTIMIZER_HINT_FORMAT, implode(self::HINT_SEPARATOR, $indexHints))
            . substr(string: $sql, offset: strlen(self::SELECT_KEYWORD));
    }

    private function resolveTableAlias(string $dqlAlias, string $index): string
    {
        $metadata = $this->getQueryComponents()[$dqlAlias]['metadata'] ?? null;
        if (false === $metadata instanceof ClassMetadata) {
            throw new LogicException(sprintf(self::UNKNOWN_ALIAS_MESSAGE, $dqlAlias, $index));
        }
        $mappedIndex = self::resolveMappedIndexes($this->getEntityManager(), $metadata)[$index]
            ?? throw new LogicException(sprintf(self::UNKNOWN_INDEX_MESSAGE, $index, $metadata->getName()));

        return $this->getSQLTableAlias($mappedIndex['table']->getTableName(), $dqlAlias);
    }

    /**
     * @param ClassMetadata<object> $metadata
     *
     * @return list<ClassMetadata<object>> the metadata of every table the alias joins, its own table first
     */
    private static function getTableMetadata(EntityManagerInterface $entityManager, ClassMetadata $metadata): array
    {
        if (false === $metadata->isInheritanceTypeJoined()) {
            return [$metadata];
        }

        return [
            $metadata,
            ...array_map(
                static fn (string $className): ClassMetadata => $entityManager->getClassMetadata($className),
                [...$metadata->parentClasses, ...$metadata->subClasses],
            ),
        ];
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private static function resolveIndexColumn(ClassMetadata $metadata, string $field): string
    {
        if ($metadata->hasField($field)) {
            return $metadata->getColumnName($field);
        }

        return $metadata->getSingleAssociationJoinColumnName($field);
    }
}
