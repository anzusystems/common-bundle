<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Validator\Constraints;

use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\Contracts\Entity\Interfaces\BaseIdentifiableInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class UniqueEntityDtoValidator extends ConstraintValidator
{
    public function __construct(
        private readonly PropertyAccessorInterface $propertyAccessor,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $userEntityClass,
    ) {
    }

    /**
     * @param UniqueEntityDto $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (false === is_object($value)) {
            throw new UnexpectedTypeException($value, 'object');
        }
        if (false === is_subclass_of($constraint->entity, BaseIdentifiableInterface::class)) {
            throw new UnexpectedTypeException($constraint->entity, BaseIdentifiableInterface::class);
        }

        /** @var class-string<BaseIdentifiableInterface> $entityClass */
        $entityClass = $constraint->entity;
        if ($entityClass === AnzuUser::class) {
            /** @var class-string<BaseIdentifiableInterface> $entityClass */
            $entityClass = $this->userEntityClass;
        }

        $fieldsNames = $constraint->fields;
        $fields = [];
        foreach ($fieldsNames as $fieldName) {
            $fields[$fieldName] = $this->propertyAccessor->getValue($value, $fieldName);
        }
        /** @var BaseIdentifiableInterface|null $existingEntity */
        $existingEntity = $this->entityManager->getRepository($entityClass)->findOneBy($fields);
        if (null === $existingEntity) {
            return;
        }

        if (false === method_exists($value, 'getId')
            || empty($value->getId())
            || $existingEntity->getId() !== $value->getId()
        ) {
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
