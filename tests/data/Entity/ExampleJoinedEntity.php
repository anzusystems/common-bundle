<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table('example_joined_entity')]
#[ORM\Index(fields: ['name'], name: ExampleJoinedEntity::IDX_NAME)]
#[ORM\Index(fields: ['joinedEntity'], name: ExampleJoinedEntity::IDX_JOINED_ENTITY)]
class ExampleJoinedEntity
{
    public const IDX_JOINED_ENTITY = 'IDX_joined_entity';
    public const IDX_NAME = 'IDX_name';

    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    private int $id;

    #[ORM\Column(type: Types::STRING)]
    private string $name;

    #[ORM\ManyToOne]
    private ExampleJoinedEntity $joinedEntity;

    public function __construct()
    {
        $this->setId(0);
        $this->setName('');
        $this->setJoinedEntity(new self());
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

    public function getJoinedEntity(): self
    {
        return $this->joinedEntity;
    }

    public function setJoinedEntity(self $joinedEntity): self
    {
        $this->joinedEntity = $joinedEntity;

        return $this;
    }
}
