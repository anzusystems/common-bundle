<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Doctrine;

use Doctrine\ORM\Query\AST\SelectStatement;
use Doctrine\ORM\Query\SqlWalker;
use UnexpectedValueException;

final class McpMaxExecutionTimeWalker extends SqlWalker
{
    public const string HINT_MAX_EXECUTION_TIME_MS = 'mcpMaxExecutionTimeMs';

    private const string SELECT_KEYWORD = 'SELECT ';
    private const string MAX_EXECUTION_TIME_HINT = '/*+ MAX_EXECUTION_TIME(%d) */ ';

    public function walkSelectStatement(SelectStatement $selectStatement): string
    {
        $sql = parent::walkSelectStatement($selectStatement);
        $maxExecutionTimeMs = $this->getQuery()
            ->getHint(self::HINT_MAX_EXECUTION_TIME_MS);
        if (false === is_int($maxExecutionTimeMs)) {
            throw new UnexpectedValueException(sprintf('Hint "%s" must be an int, "%s" given.', self::HINT_MAX_EXECUTION_TIME_MS, get_debug_type($maxExecutionTimeMs)));
        }
        $selectPosition = stripos($sql, self::SELECT_KEYWORD);
        if (false === $selectPosition) {
            throw new UnexpectedValueException(sprintf('"%s" not found in query "%s".', self::SELECT_KEYWORD, $sql));
        }

        return substr_replace(
            $sql,
            sprintf(self::MAX_EXECUTION_TIME_HINT, $maxExecutionTimeMs),
            $selectPosition + strlen(self::SELECT_KEYWORD),
            0,
        );
    }
}
