<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\AnzuTap\Node;

final class AnzuTapTableHeaderNode extends AnzuTapTableCellNode
{
    protected function getNodeName(): string
    {
        return self::TABLE_HEADER;
    }
}
