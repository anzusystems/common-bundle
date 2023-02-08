<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Validator;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class Validator
{
    private ConstraintViolationListInterface $violationList;

    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function validate(object $object, ?object $oldObject = null): void
    {
        $this->violationList = new ConstraintViolationList();
        $this->validateConstraints($object);
        $this->validateIdentity($object, $oldObject);

        if ($this->violationList->count() > 0) {
            throw new ValidationException($this->violationList);
        }
    }

    private function validateConstraints(object $object): void
    {
        $this->violationList->addAll(
            $this->validator->validate($object)
        );
    }

    private function validateIdentity(
        object $object,
        ?object $oldObject = null
    ): void {
        if (null === $oldObject
            || false === method_exists($oldObject, 'getId')
            || false === method_exists($object, 'getId')
            || $object->getId() === $oldObject->getId()
        ) {
            return;
        }

        $this->violationList->add(
            new ConstraintViolation(
                ValidationException::ERROR_ID_MISMATCH,
                ValidationException::ERROR_ID_MISMATCH,
                [],
                $object::class,
                'id',
                $object->getId(),
            )
        );
    }
}
