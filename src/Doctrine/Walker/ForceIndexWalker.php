<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Doctrine\Walker;

use Doctrine\ORM\Query\AST\FromClause;
use Doctrine\ORM\Query\SqlWalker;

final class ForceIndexWalker extends SqlWalker
{
    public const string HINT_FORCE_INDEX_FOR_FROM = 'ForceIndexWalker.ForceIndex';
    public const string HINT_FORCE_INDEX_FOR_JOIN = 'ForceIndexWalker.ForceIndexForJoin';

    private const string FORCE_INDEX_REPLACEMENT = '$1 FORCE INDEX (%s)';

    public function walkFromClause(FromClause $fromClause): string
    {
        $result = parent::walkFromClause($fromClause);

        $index = $this->getQuery()
            ->getHint(self::HINT_FORCE_INDEX_FOR_FROM);
        if ($index) {
            /** @var string $result */
            $result = preg_replace(
                pattern: '~(\bFROM\s+\w+\s+\w+)~',
                replacement: sprintf(self::FORCE_INDEX_REPLACEMENT, $index),
                subject: $result,
            );
        }

        $indexJoin = $this->getQuery()
            ->getHint(self::HINT_FORCE_INDEX_FOR_JOIN);
        foreach ($indexJoin ?: [] as $joinName => $indexName) {
            /** @var string $result */
            $result = preg_replace(
                pattern: sprintf('~(\bJOIN\s+%s\s+\w+)~', preg_quote($joinName, '~')),
                replacement: sprintf(self::FORCE_INDEX_REPLACEMENT, $indexName),
                subject: $result,
            );
        }

        return $result;
    }
}
