<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class UnionTypeExampleRequest
{
    #[Serialize]
    public int|string $value = 0;
}
