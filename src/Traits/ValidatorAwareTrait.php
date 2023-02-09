<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Traits;

use AnzuSystems\CommonBundle\Validator\Validator;
use Symfony\Contracts\Service\Attribute\Required;

trait ValidatorAwareTrait
{
    protected Validator $validator;

    #[Required]
    public function setValidator(Validator $validator): void
    {
        $this->validator = $validator;
    }
}
