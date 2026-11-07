<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\PHPStan\Domain\Example;

final class NamedArgumentsOutsideMcp
{
    public function replace(): string
    {
        return str_replace('a', 'b', 'c');
    }
}
