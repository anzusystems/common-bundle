<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\AnzuTap\TransformerProvider;

use DOMElement;
use DOMText;

interface NodeTransformerProviderInterface
{
    public function getNodeTransformerKey(DOMElement | DOMText $element): string;
}
