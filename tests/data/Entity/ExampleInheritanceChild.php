<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table('example_inheritance_child')]
#[ORM\Index(name: self::IDX_CHILD_TITLE, fields: ['title'])]
class ExampleInheritanceChild extends ExampleInheritanceParent
{
    public const string IDX_CHILD_TITLE = 'IDX_child_title';

    #[ORM\Column(type: Types::STRING)]
    private string $title = '';

    public function getTitle(): string
    {
        return $this->title;
    }
}
