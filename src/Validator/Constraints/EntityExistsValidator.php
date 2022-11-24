<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Validator\Constraints;

use AnzuSystems\CommonBundle\Repository\AnzuRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class EntityExistsValidator extends ConstraintValidator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param EntityExists $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if ($constraint->allowNull && null === $value) {
            return;
        }

        /** @var AnzuRepositoryInterface $repo */
        $repo = $this->entityManager->getRepository($constraint->entity);
        if ($repo->exists((int) $value)) {
            return;
        }

        $this->context->addViolation($constraint->message);
    }
}
