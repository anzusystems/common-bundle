<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Exception;

use AnzuSystems\Contracts\Exception\AnzuException;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class ValidationException extends AnzuException
{
    public const string ERROR_MESSAGE = 'validation_failed';
    public const string ERROR_ID_MISMATCH = 'error_id_mismatch';
    public const string ERROR_FIELD_EMPTY = 'error_field_empty';
    public const string ERROR_FIELD_LENGTH_MIN = 'error_field_length_min';
    public const string ERROR_FIELD_LENGTH_MAX = 'error_field_length_max';
    public const string ERROR_FIELD_INVALID = 'error_field_invalid';
    public const string ERROR_FIELD_UNIQUE = 'error_field_not_unique';
    public const string ERROR_FIELD_VALUE_NOT_FOUND = 'error_field_value_not_found';
    public const string ERROR_FIELD_RANGE_MIN = 'error_field_range_min';
    public const string ERROR_FIELD_RANGE_MAX = 'error_field_range_max';
    public const string ERROR_FIELD_REGEX = 'error_field_regex';
    public const string ERROR_FIELD_URL = 'error_field_url';
    public const string ERROR_ALREADY_EXISTS = 'error_already_exists';

    private ConstraintViolationListInterface $errors;
    private array $errorsFormatted = [];

    public function __construct(?ConstraintViolationListInterface $errors = null)
    {
        parent::__construct(self::ERROR_MESSAGE);
        $this->errors = $errors ?? new ConstraintViolationList();
        $this->formatErrors();
    }

    public function addFormattedError(string $field, string $message): self
    {
        $this->errorsFormatted[$field][] = $message;

        return $this;
    }

    public function getFormattedErrors(): array
    {
        return $this->errorsFormatted;
    }

    private function formatErrors(): void
    {
        foreach ($this->errors as $error) {
            $this->addFormattedError($error->getPropertyPath(), (string) $error->getMessage());
        }
    }
}
