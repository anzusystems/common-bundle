<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table('example_joined_entity')]
#[ORM\Index(fields: ['name'], name: ExampleJoinedEntity::IDX_NAME)]
class ExampleJoinedEntity
{
    public const IDX_NAME = 'IDX_name';

    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    public int $id;

    #[ORM\Column(type: Types::STRING)]
    public string $name;

    #[ORM\ManyToOne]
    public ExampleJoinedEntity $joinedEntity;
}
