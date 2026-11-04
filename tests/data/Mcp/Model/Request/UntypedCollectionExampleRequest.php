<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final class UntypedCollectionExampleRequest
{
    #[Serialize(type: HookedExampleRequest::class)]
    public Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }
}
