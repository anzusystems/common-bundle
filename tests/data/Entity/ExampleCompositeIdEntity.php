<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table('example_composite_id_entity')]
class ExampleCompositeIdEntity
{
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    private int $firstId = 0;

    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    private int $secondId = 0;

    public function getFirstId(): int
    {
        return $this->firstId;
    }

    public function getSecondId(): int
    {
        return $this->secondId;
    }
}
