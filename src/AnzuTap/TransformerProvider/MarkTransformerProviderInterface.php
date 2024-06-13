<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\AnzuTap\TransformerProvider;

use DOMElement;
use DOMText;

interface MarkTransformerProviderInterface
{
    public function getMarkTransformerKey(DOMElement | DOMText $element): string;
}
