<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\AnzuTap\Transformer\Node;

use AnzuSystems\CommonBundle\Model\AnzuTap\EmbedContainer;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapNode;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapNodeInterface;
use DOMElement;

final class HeadingTransformer extends AbstractNodeTransformer
{
    public const string NODE_NAME = 'heading';

    public static function getSupportedNodeNames(): array
    {
        return [
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
        ];
    }

    public function transform(DOMElement $element, EmbedContainer $embedContainer, AnzuTapNodeInterface $parent): AnzuTapNodeInterface
    {
        $level = (int) $element->nodeName[1];
        $level++;
        if ($level > 5) {
            $level = 5;
        }

        return new AnzuTapNode(
            type: self::NODE_NAME,
            attrs: [
                'level' => $level,
            ]
        );
    }
}
