<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\AnzuTap\Node;

final class AnzuTapDocNode extends AnzuTapNode
{
    public function __construct(
    ) {
        parent::__construct(self::DOC);
    }
}
