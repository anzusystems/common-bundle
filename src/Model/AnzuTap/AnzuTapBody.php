<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\AnzuTap;

use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapNode;

final readonly class AnzuTapBody
{
    public function __construct(
        private EmbedContainer $embedContainer,
        private AnzuTapNode $anzuTapBody,
    ) {
    }

    public function getEmbedContainer(): EmbedContainer
    {
        return $this->embedContainer;
    }

    public function getAnzuTapBody(): AnzuTapNode
    {
        return $this->anzuTapBody;
    }
}
