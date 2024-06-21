<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\AnzuTap\Transformer\Node;

use AnzuSystems\CommonBundle\Model\AnzuTap\EmbedContainer;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapNodeInterface;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapTableRowNode;
use DOMElement;

final class TableRowTransformer extends AbstractNodeTransformer
{
    public function removeWhenEmpty(): bool
    {
        return true;
    }

    public static function getSupportedNodeNames(): array
    {
        return [
            'tr',
        ];
    }

    public function transform(DOMElement $element, EmbedContainer $embedContainer, AnzuTapNodeInterface $parent): AnzuTapNodeInterface
    {
        return new AnzuTapTableRowNode();
    }
}
