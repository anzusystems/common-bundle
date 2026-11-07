<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final class PromotedExampleRequest
{
    public const int LIMIT_DEFAULT = 20;

    /**
     * @param Collection<int, int> $ids
     */
    public function __construct(
        #[Serialize]
        public string $query,
        #[Serialize]
        public int $limit = self::LIMIT_DEFAULT,
        #[Serialize]
        public Collection $ids = new ArrayCollection(),
    ) {
    }
}
