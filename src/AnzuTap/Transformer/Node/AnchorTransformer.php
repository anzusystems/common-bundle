<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\AnzuTap\Transformer\Node;

use AnzuSystems\CommonBundle\AnzuTap\Transformer\Traits\UrlTrait;
use AnzuSystems\CommonBundle\Model\AnzuTap\EmbedContainer;
use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapNodeInterface;
use DOMElement;

final class AnchorTransformer extends AbstractNodeTransformer
{
    use UrlTrait;

    public static function getSupportedNodeNames(): array
    {
        return [
            'anchor',
        ];
    }

    public function transform(DOMElement $element, EmbedContainer $embedContainer, AnzuTapNodeInterface $parent): ?AnzuTapNodeInterface
    {
        $name = trim($element->getAttribute('name'));
        if (false === ('' === $name)) {
            $parent->addAttr('anchor', self::getSanitizedAnchor($name));
        }

        return null;
    }
}
