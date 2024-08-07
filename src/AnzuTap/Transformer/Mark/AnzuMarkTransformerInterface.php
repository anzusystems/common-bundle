<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\AnzuTap\Transformer\Mark;

use AnzuSystems\CommonBundle\AnzuSystemsCommonBundle;
use DOMElement;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

interface AnzuMarkTransformerInterface
{
    public static function getSupportedNodeNames(): array;

    public function transform(DOMElement $element): array|null;

    public function supports(DOMElement $element): bool;
}
