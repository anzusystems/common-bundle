<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Doctrine\Walker;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\AST\SelectStatement;
use Doctrine\ORM\Query\SqlOutputWalker;
use LogicException;

/**
 * Forces indexes with the MySQL index-level optimizer hint INDEX(), which supersedes FORCE INDEX (to be deprecated in a
 * future MySQL release). MySQL only warns about a hint naming an unknown index and ignores it, so every index name is
 * checked against the mapping and an unknown one fails instead of the query silently losing its plan.
 */
final class ForceIndexWalker extends SqlOutputWalker
{
    public const string HINT_FORCE_INDEX_FOR_FROM = 'ForceIndexWalker.ForceIndex';
    public const string HINT_FORCE_INDEX_FOR_JOIN = 'ForceIndexWalker.ForceIndexForJoin';
    public const string UNKNOWN_INDEX_MESSAGE = 'The index "%s" is not mapped on the table "%s" of %s, so MySQL would ignore the hint forcing it.';

    private const string SELECT_KEYWORD = 'SELECT';
    private const string OPTIMIZER_HINT_FORMAT = 'SELECT /*+ %s */';
    private const string INDEX_HINT_FORMAT = 'INDEX(%s %s)';
    private const string TABLE_WITH_ALIAS_FORMAT = ' %s %s';
    private const string PRIMARY_INDEX = 'PRIMARY';
    private const string HINT_SEPARATOR = ' ';
    private const string NO_ENTITY_MESSAGE = 'The alias "%s" of the query has no entity.';

    protected function createSqlForFinalizer(SelectStatement $selectStatement): string
    {
        $sql = parent::createSqlForFinalizer($selectStatement);
        $indexHints = [
            ...$this->createFromIndexHints($selectStatement),
            ...$this->createJoinIndexHints(sql: $sql, selectStatement: $selectStatement),
        ];
        if (empty($indexHints)) {
            return $sql;
        }

        return sprintf(self::OPTIMIZER_HINT_FORMAT, implode(self::HINT_SEPARATOR, $indexHints))
            . substr(string: $sql, offset: strlen(self::SELECT_KEYWORD));
    }

    /**
     * @return list<string>
     */
    private function createFromIndexHints(SelectStatement $selectStatement): array
    {
        $index = $this->getQuery()
            ->getHint(self::HINT_FORCE_INDEX_FOR_FROM);
        if (false === is_string($index) || '' === $index) {
            return [];
        }
        $rootAlias = $this->getRootAlias($selectStatement);
        $metadata = $this->getAliasMetadata($rootAlias);
        $this->assertIndexIsMapped(metadata: $metadata, index: $index);

        return [sprintf(self::INDEX_HINT_FORMAT, $this->getSQLTableAlias($metadata->getTableName(), $rootAlias), $index)];
    }

    /**
     * A join table is the table of a joined alias or a table that class table inheritance joins to an alias; the own
     * table of the root alias is the FROM table, which HINT_FORCE_INDEX_FOR_FROM covers.
     *
     * @return list<string>
     */
    private function createJoinIndexHints(string $sql, SelectStatement $selectStatement): array
    {
        $indexByTable = $this->getQuery()
            ->getHint(self::HINT_FORCE_INDEX_FOR_JOIN);
        if (false === is_array($indexByTable) || empty($indexByTable)) {
            return [];
        }
        $rootAlias = $this->getRootAlias($selectStatement);
        $hints = [];
        foreach ($this->getQueryComponents() as $dqlAlias => $queryComponent) {
            $metadata = $queryComponent['metadata'] ?? null;
            if (false === $metadata instanceof ClassMetadata) {
                continue;
            }
            $joinedTables = $this->getJoinedTables(metadata: $metadata, isRoot: $rootAlias === (string) $dqlAlias);
            foreach (array_intersect_key($joinedTables, $indexByTable) as $tableName => $tableMetadata) {
                $tableAlias = $this->getSQLTableAlias((string) $tableName, (string) $dqlAlias);
                if (false === str_contains($sql, sprintf(self::TABLE_WITH_ALIAS_FORMAT, $tableName, $tableAlias))) {
                    continue;
                }
                $index = (string) $indexByTable[$tableName];
                $this->assertIndexIsMapped(metadata: $tableMetadata, index: $index);
                $hints[] = sprintf(self::INDEX_HINT_FORMAT, $tableAlias, $index);
            }
        }

        return $hints;
    }

    /**
     * @param ClassMetadata<object> $metadata
     *
     * @return array<string, ClassMetadata<object>> the metadata owning each table, by table name
     */
    private function getJoinedTables(ClassMetadata $metadata, bool $isRoot): array
    {
        $tables = $isRoot ? [] : [$metadata->getTableName() => $metadata];
        if (false === $metadata->isInheritanceTypeJoined()) {
            return $tables;
        }
        foreach ([...$metadata->parentClasses, ...$metadata->subClasses] as $className) {
            $classMetadata = $this->getEntityManager()
                ->getClassMetadata($className);
            $tables[$classMetadata->getTableName()] ??= $classMetadata;
        }

        return $tables;
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function assertIndexIsMapped(ClassMetadata $metadata, string $index): void
    {
        if (self::PRIMARY_INDEX === $index
            || isset($metadata->table['indexes'][$index])
            || isset($metadata->table['uniqueConstraints'][$index])
        ) {
            return;
        }

        throw new LogicException(sprintf(self::UNKNOWN_INDEX_MESSAGE, $index, $metadata->getTableName(), $metadata->getName()));
    }

    private function getRootAlias(SelectStatement $selectStatement): string
    {
        return (string) array_first($selectStatement->fromClause->identificationVariableDeclarations)
            ?->rangeVariableDeclaration
            ?->aliasIdentificationVariable;
    }

    /**
     * @return ClassMetadata<object>
     */
    private function getAliasMetadata(string $dqlAlias): ClassMetadata
    {
        $metadata = $this->getQueryComponent($dqlAlias)['metadata'] ?? null;
        if ($metadata instanceof ClassMetadata) {
            return $metadata;
        }

        throw new LogicException(sprintf(self::NO_ENTITY_MESSAGE, $dqlAlias));
    }
}
