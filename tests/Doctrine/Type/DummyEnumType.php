<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Doctrine\Type;

use AnzuSystems\CommonBundle\Doctrine\Type\AbstractEnumType;
use AnzuSystems\CommonBundle\Tests\Data\Model\Enum\DummyEnum;

final class DummyEnumType extends AbstractEnumType
{
    public function getEnumClass(): string
    {
        return DummyEnum::class;
    }
}
