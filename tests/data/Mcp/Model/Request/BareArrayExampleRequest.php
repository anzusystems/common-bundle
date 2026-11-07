<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class BareArrayExampleRequest
{
    #[Serialize]
    public array $items = [];
}
