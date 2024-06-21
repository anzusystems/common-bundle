<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\AnzuTap\TransformerProvider;

use DOMElement;
use DOMText;

final readonly class AnzuTapMarkNodeTransformerProvider implements MarkTransformerProviderInterface
{
    public function getMarkTransformerKey(DOMText|DOMElement $element): string
    {
        return $element->nodeName;
    }
}
