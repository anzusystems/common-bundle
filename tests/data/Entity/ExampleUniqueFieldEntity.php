<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table('example_unique_field_entity')]
#[ORM\Index(name: self::IDX_CODE, fields: ['code'])]
class ExampleUniqueFieldEntity
{
    public const string IDX_CODE = 'IDX_code';

    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    private int $id = 0;

    #[ORM\Column(type: Types::STRING, unique: true)]
    private string $code = '';

    public function getId(): int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }
}
