<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Traits;

use AnzuSystems\SerializerBundle\Serializer;
use Symfony\Contracts\Service\Attribute\Required;

trait SerializerAwareTrait
{
    protected Serializer $serializer;

    #[Required]
    public function setSerializer(?Serializer $serializer = null): void
    {
        if ($serializer instanceof Serializer) {
            $this->serializer = $serializer;
        }
    }
}
