<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class NotEmptyId extends Constraint
{
    public string $message = ValidationException::ERROR_FIELD_EMPTY;
}
