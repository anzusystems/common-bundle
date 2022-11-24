<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class NotEmptyIdValidator extends ConstraintValidator
{
    /**
     * @param NotEmptyId $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (is_object($value) && method_exists($value, 'getId')) {
            if (false === empty($value->getId())) {
                return;
            }
        }
        $this->context->addViolation($constraint->message);
    }
}
