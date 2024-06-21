<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Entity\Interfaces;

use AnzuSystems\Contracts\Entity\Interfaces\IdentifiableByUuidInterface;

interface EmbedKindInterface extends IdentifiableByUuidInterface
{
    public function getNodeType(): string;
}
