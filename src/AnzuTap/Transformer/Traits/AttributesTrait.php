<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\AnzuTap\Transformer\Traits;

use DOMElement;

trait AttributesTrait
{
    protected function getAttrs(array $attributes, DOMElement $element, array $validValues = []): array
    {
        $attrs = [];
        foreach ($attributes as $srcAttributeName => $dstAttributeName) {
            if (is_int($srcAttributeName)) {
                $srcAttributeName = $dstAttributeName;
            }
            $attributeValue = $element->getAttribute($srcAttributeName);
            if ($attributeValue) {
                if (isset($validValues[$dstAttributeName]) && false === in_array($attributeValue, $validValues[$dstAttributeName], true)) {
                    continue;
                }
                $attrs[$dstAttributeName] = $attributeValue;
            }
        }

        return $attrs;
    }
}
