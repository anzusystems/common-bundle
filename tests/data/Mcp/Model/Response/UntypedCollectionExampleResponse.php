<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final class UntypedCollectionExampleResponse
{
    /**
     * @return Collection without the key and item types on purpose; ECS would drop this tag without the description
     */
    #[Serialize]
    public function getItems(): Collection
    {
        return new ArrayCollection();
    }
}
