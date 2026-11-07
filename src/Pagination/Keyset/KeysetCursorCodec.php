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

/**
 * Turns the keyset values of the last row of a page into an opaque cursor (base64url JSON) and back.
 */
final readonly class KeysetCursorCodec
{
    public const string DATE_FORMAT = 'Y-m-d\TH:i:s.up';

    private const string BASE64_CHARACTERS = '+/';
    private const string BASE64URL_CHARACTERS = '-_';
    private const string BASE64_PADDING = '=';
    private const string UTC = 'UTC';

    /**
     * @param array<string, DateTimeInterface|int|string> $values
     *
     * @throws JsonException
     */
    public function encode(array $values): string
    {
        return array_map($this->encodeValue(...), $values)
            |> (static fn (array $data): string => json_encode($data, flags: JSON_THROW_ON_ERROR))
            |> base64_encode(...)
            |> (static fn (string $base64): string => strtr(string: $base64, from: self::BASE64_CHARACTERS, to: self::BASE64URL_CHARACTERS))
            |> (static fn (string $base64url): string => rtrim($base64url, self::BASE64_PADDING));
    }

    /**
     * @param array<string, string> $fieldTypes the Doctrine type (Types::DATETIME_IMMUTABLE, Types::INTEGER, Types::SMALLINT
     *                                          or Types::STRING) of each field the cursor holds
     *
     * @return array<string, DateTimeImmutable|int|string>
     *
     * @throws InvalidCursorException
     */
    public function decode(string $cursor, array $fieldTypes): array
    {
        $data = $this->decodeObject($cursor);
        if (count($data) !== count($fieldTypes)) {
            throw new InvalidCursorException();
        }

        $values = [];
        foreach ($fieldTypes as $field => $type) {
            if (false === array_key_exists($field, $data)) {
                throw new InvalidCursorException();
            }
            $values[$field] = match ($type) {
                Types::DATETIME_IMMUTABLE => $this->decodeDate($data[$field]),
                Types::INTEGER, Types::SMALLINT => $this->decodeInt($data[$field]),
                Types::STRING => $this->decodeString($data[$field]),
                default => throw new LogicException(sprintf('The keyset cursor field "%s" has the Doctrine type "%s", which a cursor cannot hold.', $field, $type)),
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
