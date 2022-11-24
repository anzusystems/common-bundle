<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Model\ValueObject;

use AnzuSystems\Contracts\Model\ValueObject\AbstractValueObject;

final class DummyValueObject extends AbstractValueObject
{
    public const TEST = 'test';
    public const ANZU = 'anzu';

    public const AVAILABLE_VALUES = [
        self::TEST,
        self::ANZU,
    ];
    public const DEFAULT_VALUE = self::TEST;
}
