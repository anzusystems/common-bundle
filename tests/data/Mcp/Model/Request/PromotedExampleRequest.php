<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class PromotedExampleRequest
{
    public const int LIMIT_DEFAULT = 20;

    public function __construct(
        #[Serialize]
        public string $query,
        #[Serialize]
        public int $limit = self::LIMIT_DEFAULT,
    ) {
    }
}
