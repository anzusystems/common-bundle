<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Repository\AnzuRepositoryInterface;
use AnzuSystems\CommonBundle\Validator\Traits\EntityClassResolverTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class EntityExistsValidator extends ConstraintValidator
{
    use EntityClassResolverTrait;

    /**
     * @param class-string $userEntityClass
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        string $userEntityClass,
    ) {
        $this->userEntityClass = $userEntityClass;
    }

    /**
     * @param EntityExists $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if ($constraint->allowNull && null === $value) {
            return;
        }

        if (false === is_int($value) && false === is_string($value)) {
            throw new UnexpectedTypeException($value, 'string|integer');
        }

        $entityClass = $this->resolveEntityClass($constraint->entity);

        /** @var AnzuRepositoryInterface $repo */
        $repo = $this->entityManager->getRepository($entityClass);
        if ($repo->exists(is_numeric($value) ? (int) $value : $value)) {
            return;
        }

        $this->context->addViolation($constraint->message);
    }
}
