<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\AnzuTap\Transformer\Traits;

use AnzuSystems\CommonBundle\Entity\Interfaces\EmbedKindInterface;

trait EmbedTrait
{
    public function getEmbed(EmbedKindInterface $embedKind): array
    {
        return [
            'type' => $embedKind->getNodeType(),
            'attrs' => [
                'id' => $embedKind->getId()->toRfc4122(),
            ],
        ];
    }
}
