<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\AnzuTap\Node;

namespace AnzuSystems\CommonBundle\Model\AnzuTap\Node;

final class AnzuTapHorizontalRuleNode extends AnzuTapNode
{
    public const string NODE_NAME = 'horizontalRule';

    public function __construct()
    {
        parent::__construct(
            type: self::NODE_NAME,
        );
    }

    protected function getMarksAllowList(): ?array
    {
        return [];
    }
}
