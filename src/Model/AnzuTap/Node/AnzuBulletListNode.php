<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\AnzuTap\Node;

class AnzuBulletListNode extends AnzuTapNode
{
    public function __construct()
    {
        parent::__construct(
            type: self::BULLET_LIST,
        );
    }

    public function isValid(): bool
    {
        return false === empty($this->content);
    }
}
