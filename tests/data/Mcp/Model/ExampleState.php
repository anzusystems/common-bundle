<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class ExampleState
{
    public function __construct(
        #[Serialize]
        public int $id,
        #[Serialize]
        public string $modifiedAt,
        #[Serialize]
        public ExampleStateItem $item,
    ) {
    }
}
