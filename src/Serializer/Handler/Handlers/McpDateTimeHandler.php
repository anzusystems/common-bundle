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
 * The pattern keeps out what the DateTimeImmutable constructor also takes: relative words ("yesterday") and an empty
 * text, which would silently mean now. A DeserializationException would end the call with an internal error, so an
 * unreadable value is an invalid argument.
 */
final class McpDateTimeHandler extends AbstractHandler
{
    public const string FORMAT_DESCRIPTION = 'ISO 8601 date-time with the UTC offset of the user\'s time zone, e.g. "2026-07-09T06:00:00+02:00"; a value without an offset is read as UTC and a date alone as the start of that day.';
    public const string END_OF_DAY_DESCRIPTION = 'Here a date alone means the end of that day.';

    /**
     * Marks the end of a period (#[Serialize(handler: McpDateTimeHandler::class, type: McpDateTimeHandler::END_OF_DAY)]):
     * an inclusive "until" read as midnight would leave out the whole last day the user asked for.
     */
    public const string END_OF_DAY = 'endOfDay';
    public const string INVALID_VALUE_MESSAGE = '"%s" is not an ISO 8601 date-time, e.g. "2026-07-09T06:00:00+02:00".';

    private const string UTC_TIMEZONE = 'UTC';
    private const string ISO_8601_PATTERN = '/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+-]\d{2}(:?\d{2})?)?)?$/';
    private const int TIME_GROUP = 1;

    public function __construct(
        private readonly DateTimeHandler $dateTimeHandler,
    ) {
    }

    public function serialize(mixed $value, Metadata $metadata, SerializationContext $context): ?string
    {
        if (null === $value) {
            return null;
        }

        // DateTimeHandler takes the custom type as its date format, which END_OF_DAY is not.
        return $this->dateTimeHandler->serialize(value: $value, metadata: clone ($metadata, ['customType' => null]), context: $context);
    }

    public function deserialize(mixed $value, Metadata $metadata): ?DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }
        if (false === is_string($value)) {
            throw $this->invalidValue($value, $metadata);
        }

        $date = $this->parse(value: $value, isEndOfDay: self::END_OF_DAY === $metadata->customType);
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
    private function parse(string $value, bool $isEndOfDay): ?DateTimeImmutable
    {
        if (1 !== preg_match(pattern: self::ISO_8601_PATTERN, subject: $value, matches: $matches)) {
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

        $date = $date->setTimezone($utc);
        if ($isEndOfDay && false === isset($matches[self::TIME_GROUP])) {
            return $date->setTime(hour: 23, minute: 59, second: 59, microsecond: 999_999);
        }

        return $date;
    }
}
