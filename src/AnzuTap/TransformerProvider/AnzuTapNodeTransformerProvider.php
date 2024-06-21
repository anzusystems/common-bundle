<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\AnzuTap\TransformerProvider;

use DOMElement;
use DOMText;

final readonly class AnzuTapNodeTransformerProvider implements NodeTransformerProviderInterface
{
    public function getNodeTransformerKey(DOMElement | DOMText $element): string
    {
        return $element->nodeName;
    }
}
