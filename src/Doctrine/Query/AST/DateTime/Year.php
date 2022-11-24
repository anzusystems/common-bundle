<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Doctrine\Query\AST\DateTime;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\PathExpression;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\Query\SqlWalker;

final class Year extends FunctionNode
{
    public PathExpression $date;

    /**
     * @throws QueryException
     */
    public function parse(Parser $parser): void
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);

        /** @var PathExpression $year */
        $year = $parser->ArithmeticPrimary();
        $this->date = $year;

        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        return 'YEAR(' . $sqlWalker->walkArithmeticPrimary($this->date) . ')';
    }
}
