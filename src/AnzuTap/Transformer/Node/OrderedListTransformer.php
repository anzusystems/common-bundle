<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\AnzuTap\Transformer\Node;

use AnzuSystems\CommonBundle\Model\AnzuTap\EmbedContainer;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapNode;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapNodeInterface;
use DOMElement;

final class OrderedListTransformer extends AbstractNodeTransformer
{
    private const string NODE_NAME = 'orderedList';
    public static function getSupportedNodeNames(): array
    {
        return [
            'ol',
            'listordered',
        ];
    }

    public function removeWhenEmpty(): bool
    {
        return true;
    }

    public function transform(DOMElement $element, EmbedContainer $embedContainer, AnzuTapNodeInterface $parent): AnzuTapNodeInterface
    {
        return new AnzuTapNode(
            type: self::NODE_NAME,
            attrs:  [
                'start' => 1,
            ]
        );
    }
}
