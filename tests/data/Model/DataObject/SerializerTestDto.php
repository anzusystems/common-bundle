<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Model\DataObject;

use AnzuSystems\CommonBundle\Tests\Data\Model\Enum\DummyEnum;
use AnzuSystems\CommonBundle\Tests\Data\Model\ValueObject\DummyValueObject;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final class SerializerTestDto
{
    #[Serialize]
    private string $name;

    #[Serialize]
    private int $position;

    #[Serialize]
    private DummyDto $dummyDto;

    #[Serialize]
    private DateTimeImmutable $createdAt;

    #[Serialize(type: 'd.m.Y H:i:s')]
    private DateTimeImmutable $createdAtFormat1;

    #[Serialize]
    private DummyValueObject $dummyValueObject;

    #[Serialize]
    private DummyEnum $dummyEnum;

    #[Serialize(type: DummyDto::class)]
    private Collection $items;

    #[Serialize(type: DummyDto::class, strategy: Serialize::KEYS_VALUES)]
    private Collection $itemsKeysValues;

    #[Serialize(type: DummyDto::class)]
    private array $itemsArray;

    #[Serialize(type: DummyDto::class, strategy: Serialize::KEYS_VALUES)]
    private array $itemsArrayKeysValues;

    public function __construct()
    {
        $this
            ->setName('')
            ->setPosition(0)
            ->setDummyDto(new DummyDto())
            ->setCreatedAt(new DateTimeImmutable())
            ->setDummyValueObject(new DummyValueObject())
            ->setDummyEnum(DummyEnum::Default)
            ->setItems(new ArrayCollection())
            ->setItemsArray([])
            ->setItemsKeysValues(new ArrayCollection())
            ->setItemsArrayKeysValues([])
        ;
    }

    #[Serialize]
    public function getCreatedAtTimestamp(): int
    {
        return $this->getCreatedAt()->getTimestamp();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getDummyDto(): DummyDto
    {
        return $this->dummyDto;
    }

    public function setDummyDto(DummyDto $dummyDto): self
    {
        $this->dummyDto = $dummyDto;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAtFormat1(): DateTimeImmutable
    {
        return $this->createdAtFormat1;
    }

    public function setCreatedAtFormat1(DateTimeImmutable $createdAtFormat1): self
    {
        $this->createdAtFormat1 = $createdAtFormat1;
        return $this;
    }

    public function getDummyValueObject(): DummyValueObject
    {
        return $this->dummyValueObject;
    }

    public function setDummyValueObject(DummyValueObject $dummyValueObject): self
    {
        $this->dummyValueObject = $dummyValueObject;

        return $this;
    }

    public function getDummyEnum(): DummyEnum
    {
        return $this->dummyEnum;
    }

    public function setDummyEnum(DummyEnum $dummyEnum): self
    {
        $this->dummyEnum = $dummyEnum;

        return $this;
    }

    public function getItems(): Collection
    {
        return $this->items;
    }

    public function setItems(Collection $items): self
    {
        $this->items = $items;

        return $this;
    }

    public function getItemsArray(): array
    {
        return $this->itemsArray;
    }

    public function setItemsArray(array $itemsArray): self
    {
        $this->itemsArray = $itemsArray;

        return $this;
    }

    public function getItemsKeysValues(): Collection
    {
        return $this->itemsKeysValues;
    }

    public function setItemsKeysValues(Collection $itemsKeysValues): self
    {
        $this->itemsKeysValues = $itemsKeysValues;

        return $this;
    }

    public function getItemsArrayKeysValues(): array
    {
        return $this->itemsArrayKeysValues;
    }

    public function setItemsArrayKeysValues(array $itemsArrayKeysValues): self
    {
        $this->itemsArrayKeysValues = $itemsArrayKeysValues;

        return $this;
    }
}
