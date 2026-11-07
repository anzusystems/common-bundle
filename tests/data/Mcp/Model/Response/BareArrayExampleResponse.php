<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class BareArrayExampleResponse
{
    #[Serialize]
    public function getItems(): array
    {
        return [];
    }
}
