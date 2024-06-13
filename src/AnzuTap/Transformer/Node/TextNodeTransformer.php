<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\AnzuTap\Transformer\Node;

use AnzuSystems\CommonBundle\AnzuTap\Transformer\Traits\TextNodeTrait;
use AnzuSystems\CommonBundle\Model\AnzuTap\EmbedContainer;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapNodeInterface;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapTextNode;
use DOMElement;
use DOMText;

class TextNodeTransformer extends AbstractNodeTransformer
{
    use TextNodeTrait;

    public static function getSupportedNodeNames(): array
    {
        return [
            '#text',
        ];
    }

    public function transform(DOMElement | DOMText $element, EmbedContainer $embedContainer, ?AnzuTapNodeInterface $parent = null): ?AnzuTapNodeInterface
    {
        $text = $this->getText($element);
        // empty text nodes are not allowed by tip-tap
        if (null === $text) {
            return null;
        }

        return new AnzuTapTextNode(
            text: $text,
        );
    }
}
