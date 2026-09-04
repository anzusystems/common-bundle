<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Request;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final readonly class GetLogsByContextRequest
{
    public function __construct(
        #[Serialize]
        public string $contextId,
    ) {
    }
}
