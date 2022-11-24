<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table('example')]
#[ORM\Index(fields: ['joinedEntity'], name: Example::IDX_JOINED_ENTITY)]
class Example
{
    public const IDX_JOINED_ENTITY = 'IDX_joined_entity';

    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    public int $id;

    #[ORM\Column(type: Types::STRING)]
    public string $name;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $createdAt;
}
