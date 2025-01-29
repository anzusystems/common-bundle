<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class UniqueEntity extends Constraint
{
    public string $message = ValidationException::ERROR_FIELD_UNIQUE;

    public function __construct(
        /** @var non-empty-list<string> */
        public readonly array $fields,
        /** @var list<string> */
        public readonly array $errorAtPath = [],
        mixed $options = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct($options, $groups, $payload);
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
