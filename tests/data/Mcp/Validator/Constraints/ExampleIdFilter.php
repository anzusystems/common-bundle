<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Compound;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class ExampleIdFilter extends Compound
{
    public const int MAX = 5;

    /**
     * @param array<string, mixed> $options
     *
     * @return list<Assert\Count>
     */
    protected function getConstraints(array $options): array
    {
        return [new Assert\Count(max: self::MAX)];
    }
}
