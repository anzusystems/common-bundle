<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Serializer\Handler\Handlers;

use AnzuSystems\CommonBundle\Mcp\Exception\McpToolArgumentsInputException;
use AnzuSystems\SerializerBundle\Context\SerializationContext;
use AnzuSystems\SerializerBundle\Handler\Handlers\AbstractHandler;
use AnzuSystems\SerializerBundle\Handler\Handlers\DateTimeHandler;
use AnzuSystems\SerializerBundle\Metadata\Metadata;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Reads an ISO 8601 date, optionally with a time and an offset, where DateTimeHandler requires one fixed format and
 * would refuse a date without a time or a value without an offset. The pattern keeps out what the DateTimeImmutable
 * constructor also takes: relative words ("yesterday", "+3 days") and an empty text, which would silently mean now.
 * The SDK checks the arguments against the schema only, so an unreadable value is answered as an invalid argument; a
 * DeserializationException would end the call with an internal error.
 */
final class McpDateTimeHandler extends AbstractHandler
{
    public const string FORMAT_DESCRIPTION = 'ISO 8601 date-time, e.g. "2026-07-09T06:00:00+02:00"; any UTC offset is honoured and a value without an offset is read as UTC.';
    public const string INVALID_VALUE_MESSAGE = '"%s" is not an ISO 8601 date-time, e.g. "2026-07-09T06:00:00+02:00".';

    private const string UTC_TIMEZONE = 'UTC';
    private const string ISO_8601_PATTERN = '/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+-]\d{2}:?\d{2})?)?$/';

    public function __construct(
        private readonly DateTimeHandler $dateTimeHandler,
    ) {
    }

    public function serialize(mixed $value, Metadata $metadata, SerializationContext $context): ?string
    {
        if (null === $value) {
            return null;
        }

        return $this->dateTimeHandler->serialize(value: $value, metadata: $metadata, context: $context);
    }

    public function deserialize(mixed $value, Metadata $metadata): ?DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }
        if (false === is_string($value)) {
            throw $this->invalidValue($value, $metadata);
        }

        $date = $this->parse($value);
        if (null === $date) {
            throw McpToolArgumentsInputException::createInvalidArgument(
                propertyPath: (string) $metadata->property,
                message: sprintf(self::INVALID_VALUE_MESSAGE, $value),
            );
        }

        return $date;
    }

    /**
     * The constructor rolls an impossible date over ("2026-02-31" becomes 3 March) and only warns, so a warning refuses it.
     */
    private function parse(string $value): ?DateTimeImmutable
    {
        if (1 !== preg_match(pattern: self::ISO_8601_PATTERN, subject: $value)) {
            return null;
        }
        $utc = new DateTimeZone(self::UTC_TIMEZONE);
        try {
            $date = new DateTimeImmutable($value, $utc);
        } catch (DateMalformedStringException) {
            return null;
        }
        $errors = DateTimeImmutable::getLastErrors();
        if (is_array($errors) && $errors['warning_count'] > 0) {
            return null;
        }

        return $date->setTimezone($utc);
    }
}
