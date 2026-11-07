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

    public function testCursorHoldsTheValuesWithTheDateInUtcAsUrlSafeBase64(): void
    {
        $cursor = new KeysetCursorCodec()
            ->encode($this->createValues());

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $cursor);
        self::assertSame(
            [
                'shownSince' => self::SHOWN_SINCE_UTC,
                'id' => self::ID,
                'slug' => self::SLUG,
            ],
            json_decode((string) base64_decode(strtr(string: $cursor, from: '-_', to: '+/'), strict: true), associative: true),
        );
    }

    public function testDecodedCursorGivesBackTheValuesInTheDefaultTimeZone(): void
    {
        $values = $this->createValues();
        $codec = new KeysetCursorCodec();

        $decoded = $codec->decode($codec->encode($values), self::FIELD_TYPES);

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
            ->decode($cursor, self::FIELD_TYPES);
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
            'JSON list' => [self::toCursor('["' . self::SHOWN_SINCE_UTC . '", 1, "a"]')],
            'missing field' => [self::toCursor('{"shownSince":"' . self::SHOWN_SINCE_UTC . '","id":1}')],
            'unknown field instead of a known one' => [self::toCursor('{"shownSince":"' . self::SHOWN_SINCE_UTC . '","id":1,"name":"a"}')],
            'extra field' => [self::toCursor('{"shownSince":"' . self::SHOWN_SINCE_UTC . '","id":1,"slug":"a","name":"a"}')],
            'unreadable date' => [self::toCursor('{"shownSince":"yesterday","id":1,"slug":"a"}')],
            'date without microseconds' => [self::toCursor('{"shownSince":"2026-08-21T08:00:00Z","id":1,"slug":"a"}')],
            'date not a string' => [self::toCursor('{"shownSince":1,"id":1,"slug":"a"}')],
            'id not a number' => [self::toCursor('{"shownSince":"' . self::SHOWN_SINCE_UTC . '","id":"x","slug":"a"}')],
            'id not an integer' => [self::toCursor('{"shownSince":"' . self::SHOWN_SINCE_UTC . '","id":1.5,"slug":"a"}')],
            'slug not a string' => [self::toCursor('{"shownSince":"' . self::SHOWN_SINCE_UTC . '","id":1,"slug":null}')],
        ];
    }

    public function testFieldOfAnUnsupportedTypeIsALogicException(): void
    {
        $this->expectException(LogicException::class);

        new KeysetCursorCodec()
            ->decode(self::toCursor('{"amount":"1.5"}'), ['amount' => Types::DECIMAL]);
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

    private static function toCursor(string $json): string
    {
        return rtrim(strtr(string: base64_encode($json), from: '+/', to: '-_'), '=');
    }
}
