<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Doctrine\Walker;

use Doctrine\ORM\Query\SqlWalker;

final class ForceIndexWalker extends SqlWalker
{
    public const HINT_FORCE_INDEX_FOR_FROM = 'ForceIndexWalker.ForceIndex';
    public const HINT_FORCE_INDEX_FOR_JOIN = 'ForceIndexWalker.ForceIndexForJoin';

    public function walkFromClause($fromClause): string
    {
        $result = parent::walkFromClause($fromClause);

        $index = $this->getQuery()->getHint(self::HINT_FORCE_INDEX_FOR_FROM);
        if ($index) {
            $result = preg_replace('~(\bFROM\s*\w+\s*\w+)~', sprintf('$1 FORCE INDEX (%s)', $index), $result);
        }

        $indexJoin = $this->getQuery()->getHint(self::HINT_FORCE_INDEX_FOR_JOIN);
        foreach ($indexJoin ?: [] as $joinName => $indexName) {
            $result = preg_replace("~(\bJOIN\s*${joinName}\s*\w+)~", sprintf('$1 FORCE INDEX (%s)', $indexName), $result);
        }

        return $result;
    }
}
