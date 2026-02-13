<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\AnzuTap\Transformer\Mark;

use DOMElement;

abstract class AbstractMarkNodeTransformer implements AnzuMarkTransformerInterface
{
    public function supports(DOMElement $element): bool
    {
        return true;
    }

    protected function getMarkNode(string $nodeName, array $map, ?array $attributes = null): ?array
    {
        if (false === isset($map[$nodeName])) {
            return null;
        }

        $mark = [
            'type' => $map[$nodeName],
        ];

        if (null !== $attributes) {
            $mark['attrs'] = $attributes;
        }

        return $mark;
    }
}
