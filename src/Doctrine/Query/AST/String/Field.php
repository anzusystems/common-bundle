<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Doctrine\Query\AST\String;

use Doctrine\ORM\Query\AST\ASTException;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\AST\PathExpression;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\Query\SqlWalker;

final class Field extends FunctionNode
{
    private PathExpression $field;

    /**
     * @var array<int, Node>
     */
    private array $values = [];

    /**
     * @throws QueryException
     */
    public function parse(Parser $parser): void
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);

        // Do the field.
        /** @var PathExpression $field */
        $field = $parser->ArithmeticPrimary();
        $this->field = $field;

        /**
         * @psalm-suppress PossiblyNullReference
         * @psalm-suppress PossiblyNullArrayAccess
         */
        while (Lexer::T_CLOSE_PARENTHESIS !== $parser->getLexer()->lookahead['type']) {
            $parser->match(Lexer::T_COMMA);
            /** @var Node $node */
            $node = $parser->ArithmeticPrimary();
            $this->values[] = $node;
        }

        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }

    /**
     * @throws ASTException
     */
    public function getSql(SqlWalker $sqlWalker): string
    {
        $query = 'FIELD(';
        $query .= $this->field->dispatch($sqlWalker);
        $query .= ', ';

        foreach ($this->values as $index => $value) {
            if ($index > 0) {
                $query .= ', ';
            }
            $query .= $value->dispatch($sqlWalker);
        }

        $query .= ')';

        return $query;
    }
}
