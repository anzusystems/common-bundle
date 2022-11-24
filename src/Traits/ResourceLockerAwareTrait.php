<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Traits;

use AnzuSystems\CommonBundle\Util\ResourceLocker;
use Symfony\Contracts\Service\Attribute\Required;

trait ResourceLockerAwareTrait
{
    protected ResourceLocker $resourceLocker;

    #[Required]
    public function setResourceLocker(ResourceLocker $resourceLocker): void
    {
        $this->resourceLocker = $resourceLocker;
    }
}
