<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Model\DataObject;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class BsonDocumentDto
{
    #[Serialize]
    public string $publicProperty = '';

    #[Serialize]
    public private(set) string $privateSetProperty = '';

    #[Serialize]
    private string $withSetter = '';

    public function getWithSetter(): string
    {
        return $this->withSetter;
    }

    public function setWithSetter(string $withSetter): void
    {
        $this->withSetter = strtoupper($withSetter);
    }
}
