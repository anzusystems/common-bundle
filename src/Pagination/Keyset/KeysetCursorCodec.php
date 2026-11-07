<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Pagination\Keyset;

use AnzuSystems\CommonBundle\Helper\StringHelper;
use AnzuSystems\CommonBundle\Pagination\Keyset\Exception\InvalidCursorException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use JsonException;
use LogicException;

final readonly class KeysetCursorCodec
{
    public const string DATE_FORMAT = 'Y-m-d\TH:i:s.up';
    public const array FIELD_TYPES = [
        Types::DATETIME_IMMUTABLE => self::DATE_VALUE,
        Types::INTEGER => self::INT_VALUE,
        Types::SMALLINT => self::INT_VALUE,
        Types::STRING => self::STRING_VALUE,
    ];

    private const string DATE_VALUE = 'date';
    private const string INT_VALUE = 'int';
    private const string STRING_VALUE = 'string';
    private const string QUERY_KEY = 'query';
    private const string VALUES_KEY = 'values';
    private const string BASE64_CHARACTERS = '+/';
    private const string BASE64URL_CHARACTERS = '-_';
    private const string BASE64_PADDING = '=';
    private const string UTC = 'UTC';

    /**
     * @param array<string, DateTimeInterface|int|string> $values
     * @param string $query the fingerprint of the query the cursor continues
     *
     * @throws JsonException
     */
    public function encode(array $values, string $query): string
    {
        return [
            self::QUERY_KEY => $query,
            self::VALUES_KEY => array_map($this->encodeValue(...), $values),
        ]
            |> (static fn (array $data): string => json_encode($data, flags: JSON_THROW_ON_ERROR))
            |> base64_encode(...)
            |> (static fn (string $base64): string => strtr(string: $base64, from: self::BASE64_CHARACTERS, to: self::BASE64URL_CHARACTERS))
            |> (static fn (string $base64url): string => rtrim($base64url, self::BASE64_PADDING));
    }

    /**
     * @param array<string, string> $fieldTypes the Doctrine type (a key of FIELD_TYPES) of each field the cursor holds
     * @param string $query the fingerprint of the query, which must be the one the cursor was written for
     *
     * @return array<string, DateTimeImmutable|int|string>
     *
     * @throws InvalidCursorException
     */
    public function decode(string $cursor, array $fieldTypes, string $query): array
    {
        $cursorData = $this->decodeObject($cursor);
        if (array_keys($cursorData) !== [self::QUERY_KEY, self::VALUES_KEY] || $cursorData[self::QUERY_KEY] !== $query) {
            throw new InvalidCursorException();
        }
        $data = $cursorData[self::VALUES_KEY];
        if (false === is_array($data) || array_is_list($data) || count($data) !== count($fieldTypes)) {
            throw new InvalidCursorException();
        }

        $values = [];
        foreach ($fieldTypes as $field => $type) {
            if (false === array_key_exists($field, $data)) {
                throw new InvalidCursorException();
            }
            $values[$field] = match (self::FIELD_TYPES[$type] ?? null) {
                self::DATE_VALUE => $this->decodeDate($data[$field]),
                self::INT_VALUE => $this->decodeInt($data[$field]),
                self::STRING_VALUE => $this->decodeString($data[$field]),
                null => throw new LogicException(sprintf('The keyset cursor field "%s" has the Doctrine type "%s", which a cursor cannot hold.', $field, $type)),
            };
        }

        return $values;
    }

    private function encodeValue(DateTimeInterface|int|string $value): int|string
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value)
                ->setTimezone(new DateTimeZone(self::UTC))
                ->format(self::DATE_FORMAT);
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidCursorException
     */
    private function decodeObject(string $cursor): array
    {
        $json = base64_decode(strtr(string: $cursor, from: self::BASE64URL_CHARACTERS, to: self::BASE64_CHARACTERS), strict: true);
        if (false === is_string($json)) {
            throw new InvalidCursorException();
        }

        try {
            $data = json_decode(json: $json, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidCursorException();
        }
        if (false === is_array($data) || array_is_list($data)) {
            throw new InvalidCursorException();
        }

        return $data;
    }

    /**
     * @throws InvalidCursorException
     */
    private function decodeDate(mixed $value): DateTimeImmutable
    {
        if (false === is_string($value)) {
            throw new InvalidCursorException();
        }
        $date = DateTimeImmutable::createFromFormat('!' . self::DATE_FORMAT, $value);
        if (false === $date || StringHelper::isNotSame($date->format(self::DATE_FORMAT), $value)) {
            throw new InvalidCursorException();
        }

        // DBAL writes a date parameter as it is, without converting it, so it must be in the zone of the hydrated value.
        return $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
    }

    /**
     * @throws InvalidCursorException
     */
    private function decodeInt(mixed $value): int
    {
        if (false === is_int($value)) {
            throw new InvalidCursorException();
        }

        return $value;
    }

    /**
     * @throws InvalidCursorException
     */
    private function decodeString(mixed $value): string
    {
        if (false === is_string($value)) {
            throw new InvalidCursorException();
        }

        return $value;
    }
}
