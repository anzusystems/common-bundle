<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request;

use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

final class CollectionExampleRequest
{
    public const int ITEMS_MAX = 5;

    /**
     * @var Collection<int, HookedExampleRequest>
     */
    #[Assert\Count(min: 1, max: self::ITEMS_MAX)]
    #[Assert\Valid]
    #[Serialize(type: HookedExampleRequest::class)]
    public Collection $items;

    /**
     * @var list<HookedExampleRequest>
     */
    #[Assert\Count(min: 1, max: self::ITEMS_MAX)]
    #[Assert\Valid]
    #[Serialize(type: HookedExampleRequest::class)]
    public array $itemList;

    /**
     * @var Collection<int, HookedExampleRequest>|null
     */
    #[Serialize(type: HookedExampleRequest::class)]
    public ?Collection $optionalItems = null;

    /**
     * @var Collection<string, HookedExampleRequest>
     */
    #[Serialize(type: HookedExampleRequest::class, strategy: Serialize::KEYS_VALUES)]
    public Collection $itemsByKey;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->itemsByKey = new ArrayCollection();
    }
}
