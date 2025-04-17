<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\AnzuTap\Node;

final class AnzuTapTableRowNode extends AnzuTapNode
{
    public function __construct(?array $attrs = null)
    {
        parent::__construct(
            type: self::TABLE_ROW,
            attrs: $attrs
        );
    }
    protected function getMarksAllowList(): array
    {
        return [];
    }
}
