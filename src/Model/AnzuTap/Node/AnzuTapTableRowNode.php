<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\AnzuTap\Node;

final class AnzuTapTableRowNode extends AnzuTapNode
{
    public const string NODE_NAME = 'tableRow';

    public function __construct(?array $attrs = null)
    {
        parent::__construct(
            type: self::NODE_NAME,
            attrs: $attrs
        );
    }
}
