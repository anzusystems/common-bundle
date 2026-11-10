<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Pagination\Keyset;

use AnzuSystems\CommonBundle\Pagination\Keyset\Exception\InvalidCursorException;
use AnzuSystems\CommonBundle\Pagination\Keyset\KeysetCursorCodec;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use LogicException;
use PHPUnit\Framework\TestCase;

final class KeysetCursorCodecTest extends TestCase
{
    private const array FIELD_TYPES = [
        'shownSince' => Types::DATETIME_IMMUTABLE,
        'id' => Types::INTEGER,
        'slug' => Types::STRING,
    ];
    private const string SHOWN_SINCE_UTC = '2026-08-21T08:00:00.123456Z';
    private const int ID = 42;
    private const string SLUG = 'a/b+c?';
    private const string MICROSECOND_TIMESTAMP = 'U.u';
    private const string QUERY = 'query-fingerprint';
    private const string BASE64_CHARACTERS = '+/';
    private const string BASE64URL_CHARACTERS = '-_';
    private const string VALUES = '{"shownSince":"' . self::SHOWN_SINCE_UTC . '","id":1,"slug":"a"}';

    public function testCursorHoldsTheValuesWithTheDateInUtcAsUrlSafeBase64(): void
    {
        $cursor = new KeysetCursorCodec()
            ->encode(values: $this->createValues(), query: self::QUERY);

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $cursor);
        self::assertSame(
            [
                'query' => self::QUERY,
                'values' => [
                    'shownSince' => self::SHOWN_SINCE_UTC,
                    'id' => self::ID,
                    'slug' => self::SLUG,
                ],
            ],
            json_decode((string) base64_decode(strtr(string: $cursor, from: self::BASE64URL_CHARACTERS, to: self::BASE64_CHARACTERS), strict: true), associative: true),
        );
    }

    public function testDecodedCursorGivesBackTheValuesInTheDefaultTimeZone(): void
    {
        $values = $this->createValues();
        $codec = new KeysetCursorCodec();

        $decoded = $codec->decode(
            cursor: $codec->encode(values: $values, query: self::QUERY),
            fieldTypes: self::FIELD_TYPES,
            query: self::QUERY,
        );

        self::assertSame(array_keys(self::FIELD_TYPES), array_keys($decoded));
        self::assertInstanceOf(DateTimeImmutable::class, $decoded['shownSince']);
        self::assertSame(
            $values['shownSince']->format(self::MICROSECOND_TIMESTAMP),
            $decoded['shownSince']->format(self::MICROSECOND_TIMESTAMP),
        );
        self::assertSame(date_default_timezone_get(), $decoded['shownSince']->getTimezone()->getName());
        self::assertSame(self::ID, $decoded['id']);
        self::assertSame(self::SLUG, $decoded['slug']);
    }

    /**
     * @dataProvider invalidCursorProvider
     */
    public function testInvalidCursorIsAnInvalidCursorException(string $cursor): void
    {
        $this->expectExceptionObject(new InvalidCursorException());

        new KeysetCursorCodec()
            ->decode(cursor: $cursor, fieldTypes: self::FIELD_TYPES, query: self::QUERY);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidCursorProvider(): array
    {
        return [
            'empty' => [''],
            'not base64' => ['not a cursor!'],
            'not JSON' => [self::toCursor('shownSince')],
            'JSON scalar' => [self::toCursor('5')],
            'JSON list' => [self::toCursor('["' . self::QUERY . '", ' . self::VALUES . ']')],
            'values without the query' => [self::toCursor(self::VALUES)],
            'query of another list' => [self::toCursor('{"query":"another","values":' . self::VALUES . '}')],
            'extra key next to the query and the values' => [self::toCursor('{"query":"' . self::QUERY . '","values":' . self::VALUES . ',"page":2}')],
            'values a list' => [self::toValuesCursor('["' . self::SHOWN_SINCE_UTC . '", 1, "a"]')],
            'missing field' => [self::toValuesCursor('{"shownSince":"' . self::SHOWN_SINCE_UTC . '","id":1}')],
            'unknown field instead of a known one' => [self::toValuesCursor('{"shownSince":"' . self::SHOWN_SINCE_UTC . '","id":1,"name":"a"}')],
            'extra field' => [self::toValuesCursor('{"shownSince":"' . self::SHOWN_SINCE_UTC . '","id":1,"slug":"a","name":"a"}')],
            'unreadable date' => [self::toValuesCursor('{"shownSince":"yesterday","id":1,"slug":"a"}')],
            'date without microseconds' => [self::toValuesCursor('{"shownSince":"2026-08-21T08:00:00Z","id":1,"slug":"a"}')],
            'date not a string' => [self::toValuesCursor('{"shownSince":1,"id":1,"slug":"a"}')],
            'id not a number' => [self::toValuesCursor('{"shownSince":"' . self::SHOWN_SINCE_UTC . '","id":"x","slug":"a"}')],
            'id not an integer' => [self::toValuesCursor('{"shownSince":"' . self::SHOWN_SINCE_UTC . '","id":1.5,"slug":"a"}')],
            'slug not a string' => [self::toValuesCursor('{"shownSince":"' . self::SHOWN_SINCE_UTC . '","id":1,"slug":null}')],
        ];
    }

    public function testFieldOfAnUnsupportedTypeIsALogicException(): void
    {
        $this->expectException(LogicException::class);

        new KeysetCursorCodec()
            ->decode(cursor: self::toValuesCursor('{"amount":"1.5"}'), fieldTypes: ['amount' => Types::DECIMAL], query: self::QUERY);
    }

    /**
     * @return array{shownSince: DateTimeImmutable, id: int, slug: string}
     */
    private function createValues(): array
    {
        return [
            'shownSince' => new DateTimeImmutable('2026-08-21 10:00:00.123456', new DateTimeZone('Europe/Bratislava')),
            'id' => self::ID,
            'slug' => self::SLUG,
        ];
    }

    private static function toValuesCursor(string $valuesJson): string
    {
        return self::toCursor('{"query":"' . self::QUERY . '","values":' . $valuesJson . '}');
    }

    private static function toCursor(string $json): string
    {
        return rtrim(strtr(string: base64_encode($json), from: self::BASE64_CHARACTERS, to: self::BASE64URL_CHARACTERS), '=');
    }
}
