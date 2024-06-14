<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\AnzuTap\Transformer\Traits;

use DOMElement;
use DOMText;

trait TextNodeTrait
{
    protected function getText(DOMText | DOMElement $element): ?string
    {
        $text = $element->textContent;
        $textToTrim = $text;
        if ('' === trim($textToTrim)) {
            return null;
        }

        $text = preg_replace('/\s{2,}/', ' ', $text);
        $text = preg_replace('/(\xc2\xa0|\xc2\xa0\s|\s\xc2\xa0)+/', "\xc2\xa0", $text);

        return preg_replace('/(\xc2\xa0\s)+/', "\xc2\xa0", $text);
    }
}
