<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Validator\Constraints;

use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\Contracts\Entity\Interfaces\BaseIdentifiableInterface;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class UniqueEntityValidator extends ConstraintValidator
{
    /**
     * @param class-string $userEntityClass
     */
    public function __construct(
        private readonly PropertyAccessorInterface $propertyAccessor,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $userEntityClass,
    ) {
    }

    /**
     * @param UniqueEntity $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (false === ($value instanceof BaseIdentifiableInterface)) {
            throw new UnexpectedTypeException($constraint, BaseIdentifiableInterface::class);
        }

        $fieldsNames = $constraint->fields;
        $fields = [];
        foreach ($fieldsNames as $fieldName) {
            $fields[$fieldName] = $this->propertyAccessor->getValue($value, $fieldName);
        }

        $entityClass = ClassUtils::getRealClass($value::class);
        if ($entityClass === AnzuUser::class) {
            $entityClass = $this->userEntityClass;
        }

        /** @var BaseIdentifiableInterface|null $existingEntity */
        $existingEntity = $this->entityManager->getRepository($entityClass)->findOneBy($fields);
        if (null === $existingEntity) {
            return;
        }
        if (empty($value->getId()) || $existingEntity->isNot($value)) {
            foreach ($constraint->errorAtPath ?: $fieldsNames as $fieldsName) {
                $this->context
                    ->buildViolation($constraint->message)
                    ->atPath($fieldsName)
                    ->addViolation()
                ;
            }
        }
    }
}
