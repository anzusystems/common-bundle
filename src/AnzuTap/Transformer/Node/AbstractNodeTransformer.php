<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\AnzuTap\Transformer\Node;

use AnzuSystems\CommonBundle\Model\AnzuTap\Node\AnzuTapNodeInterface;

abstract class AbstractNodeTransformer implements AnzuNodeTransformerInterface
{
    public static function getSupportedNodeNames(): array
    {
        return [];
    }

    public function skipChildren(): bool
    {
        return false;
    }

    public function removeWhenEmpty(): bool
    {
        return false;
    }

    public function fixEmpty(AnzuTapNodeInterface $node): void
    {
    }
}
