<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Model\DataObject;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class DummyDto
{
    #[Serialize]
    private string $data = '';

    public function getData(): string
    {
        return $this->data;
    }

    public function setData(string $data): self
    {
        $this->data = $data;

        return $this;
    }
}
