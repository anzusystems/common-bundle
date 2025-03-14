<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\AnzuTap\Node;

use Symfony\Component\Uid\Uuid;

class AnzuTapEmbedNodeNode extends AnzuTapNode
{
    public function __construct(string $type, Uuid $id)
    {
        parent::__construct(
            type: $type,
            attrs: [
                'id' => $id->toRfc4122(),
            ],
        );
    }

    protected function getMarksAllowList(): array
    {
        return [];
    }
}
