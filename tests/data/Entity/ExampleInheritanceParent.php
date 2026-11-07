<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table('example_inheritance_parent')]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'discriminator', type: Types::STRING)]
#[ORM\DiscriminatorMap(['child' => ExampleInheritanceChild::class])]
#[ORM\Index(name: self::IDX_PARENT_NAME, fields: ['name'])]
abstract class ExampleInheritanceParent
{
    public const string IDX_PARENT_NAME = 'IDX_parent_name';

    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    private int $id = 0;

    #[ORM\Column(type: Types::STRING)]
    private string $name = '';

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
