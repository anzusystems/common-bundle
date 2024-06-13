<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\AnzuTap\TransformerProvider;

use DOMElement;
use DOMText;

final readonly class AnzuTapNodeTransformerProvider implements NodeTransformerProviderInterface
{
    public static function getContentTransformerKey(string $nodeName, ?string $type = null, ?string $subtype = null): string
    {
        if (null === $type) {
            return $nodeName;
        }

        if (null === $subtype) {
            return $nodeName . '_' . $type;
        }

        return $nodeName . '_' . $type . '_' . $subtype;
    }

    public function getNodeTransformerKey(DOMElement | DOMText $element): string
    {
        return $element instanceof DOMElement
            ? self::getContentTransformerKey(
                $element->nodeName,
                $this->getStringOrNullAttribute($element, 'type'),
                $this->getStringOrNullAttribute($element, 'subtype')
            )
            : self::getContentTransformerKey($element->nodeName)
        ;
    }

    private function getStringOrNullAttribute(DOMElement $element, string $attribute): ?string
    {
        $attr = $element->getAttribute($attribute);
        if (empty($attr)) {
            return null;
        }

        return $attr;
    }
}
