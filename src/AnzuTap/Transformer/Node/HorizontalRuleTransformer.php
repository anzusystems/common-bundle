<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\AnzuTap\Transformer\Node;

use AnzuSystems\CommonBundle\Model\AnzuTap\EmbedContainer;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapNode;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapNodeInterface;
use DOMElement;

final class HorizontalRuleTransformer extends AbstractNodeTransformer
{
    private const string NODE_NAME = 'horizontalRule';
    public static function getSupportedNodeNames(): array
    {
        return [
            'hr',
        ];
    }

    public function transform(DOMElement $element, EmbedContainer $embedContainer, AnzuTapNodeInterface $parent): AnzuTapNodeInterface
    {
        return new AnzuTapNode(
            type: self::NODE_NAME,
        );
    }
}
