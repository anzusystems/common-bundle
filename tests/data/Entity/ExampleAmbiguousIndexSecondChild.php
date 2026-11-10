<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table('example_ambiguous_index_second_child')]
#[ORM\Index(name: ExampleAmbiguousIndexParent::IDX_NAME, fields: ['title'])]
class ExampleAmbiguousIndexSecondChild extends ExampleAmbiguousIndexParent
{
    #[ORM\Column(type: Types::STRING)]
    private string $title = '';

    public function getTitle(): string
    {
        return $this->title;
    }
}
