<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum DummyEnum: string implements EnumInterface
{
    use BaseEnumTrait;

    case StateOne = 'state_one';
    case StateTwo = 'state_two';
    case StateThree = 'state_three';

    public const Default = self::StateOne;
}
