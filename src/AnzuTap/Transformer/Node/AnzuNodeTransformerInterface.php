<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\AnzuTap\Transformer\Node;

use AnzuSystems\CommonBundle\Entity\Interfaces\EmbedKindInterface;
use AnzuSystems\CommonBundle\Model\AnzuTap\EmbedContainer;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapNodeInterface;
use DOMElement;

interface AnzuNodeTransformerInterface
{
    public static function getSupportedNodeNames(): array;

    public function transform(DOMElement $element, EmbedContainer $embedContainer, AnzuTapNodeInterface $parent): null|AnzuTapNodeInterface|EmbedKindInterface;

    public function skipChildren(): bool;

    public function removeWhenEmpty(): bool;
}
