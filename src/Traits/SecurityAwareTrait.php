<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Traits;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Service\Attribute\Required;

trait SecurityAwareTrait
{
    protected Security $security;

    #[Required]
    public function setSecurity(Security $security): void
    {
        $this->security = $security;
    }
}
