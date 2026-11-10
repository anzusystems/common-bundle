<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\PHPStan\Domain\Example;

use AnzuSystems\CommonBundle\Tests\Data\Entity\Example;

final class ObjectArraysOutsideMcp
{
    /**
     * @param list<Example> $examples
     *
     * @return list<Example>
     */
    public function filterExamples(array $examples): array
    {
        return $examples;
    }
}
