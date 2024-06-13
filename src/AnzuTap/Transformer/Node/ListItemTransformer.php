<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\AnzuTap\Transformer\Node;

use AnzuSystems\CommonBundle\Model\AnzuTap\EmbedContainer;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapListItemNode;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapNodeInterface;
use DOMElement;

final class ListItemTransformer extends AbstractNodeTransformer
{
    public static function getSupportedNodeNames(): array
    {
        return [
            'li',
        ];
    }

    public function removeWhenEmpty(): bool
    {
        return true;
    }

    public function transform(DOMElement $element, EmbedContainer $embedContainer, AnzuTapNodeInterface $parent): AnzuTapNodeInterface
    {
        return new AnzuTapListItemNode();
    }
}
