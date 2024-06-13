<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\AnzuTap\Node;

final class AnzuTapTableHeaderNode extends AnzuTapTableCellNode
{
    public const string NODE_NAME = 'tableHeader';

    protected function getNodeName(): string
    {
        return self::NODE_NAME;
    }
}
