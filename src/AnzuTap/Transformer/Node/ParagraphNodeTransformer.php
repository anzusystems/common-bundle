<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\AnzuTap\Transformer\Node;

use AnzuSystems\CommonBundle\Model\AnzuTap\EmbedContainer;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapNodeInterface;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapParagraphNode;
use DOMElement;

class ParagraphNodeTransformer extends AbstractNodeTransformer
{
    public static function getSupportedNodeNames(): array
    {
        return [
            'p',
            'caption',
            'quote',
            'pre',
            'address',
            'code',
        ];
    }

    public function transform(DOMElement $element, EmbedContainer $embedContainer, AnzuTapNodeInterface $parent): AnzuTapNodeInterface
    {
        return new AnzuTapParagraphNode();
    }
}
