<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Entity;

use AnzuSystems\CommonBundle\Doctrine\Type\EnumType;
use AnzuSystems\CommonBundle\Tests\Data\Model\Enum\DummyEnum;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table('example')]
class Example
{
    public const EXAMPLE_INSTANCE_ID = 1;

    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    #[Serialize]
    private int $id;

    #[ORM\Column(type: Types::STRING)]
    #[Serialize]
    private string $name;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    #[Serialize]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: EnumType::TYPE, enumType: DummyEnum::class)]
    #[Serialize]
    private DummyEnum $dummyEnum = DummyEnum::Default;

    public function __construct()
    {
        $this->setId(self::EXAMPLE_INSTANCE_ID);
        $this->setName('');
        $this->setCreatedAt(new DateTimeImmutable());
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
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

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

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
}
