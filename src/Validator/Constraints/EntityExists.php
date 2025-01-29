<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class EntityExists extends Constraint
{
    public const string MESSAGE = 'entity_not_exists';

    public string $message = self::MESSAGE;

    /**
     * @param class-string $entity
     */
    public function __construct(
        public string $entity,
        public bool $allowNull = false,
        ?array $options = null,
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct($options ?? [], $groups, $payload);
    }
}
