<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\AnzuTap;

use AnzuSystems\Contracts\Entity\Interfaces\IdentifiableByUuidInterface;

class EmbedContainer
{
    /**
     * @var array<string, IdentifiableByUuidInterface>
     */
    private array $embeds = [];

    public function addEmbed(IdentifiableByUuidInterface $embedDto): self
    {
        $this->embeds[$embedDto->getId()->toRfc4122()] = $embedDto;

        return $this;
    }

    /**
     * @return array<string, IdentifiableByUuidInterface>
     */
    public function getEmbeds(): array
    {
        return $this->embeds;
    }
}
