<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request;

use AnzuSystems\CommonBundle\Tests\Data\Model\Enum\DummyEnum;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class EnumExampleRequest
{
    #[Serialize]
    public DummyEnum $state = DummyEnum::Default;
}
