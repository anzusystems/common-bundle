<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Helper;

use AnzuSystems\CommonBundle\Helper\MongoHelper;
use DateTimeImmutable;
use MongoDB\BSON\UTCDateTime;
use PHPUnit\Framework\TestCase;

final class MongoHelperTest extends TestCase
{
    private const string DATETIME_FIELD = 'datetime';

    /**
     * @dataProvider objectIdBoundsProvider
     */
    public function testObjectIdBounds(string $expectedTimestampHex, DateTimeImmutable $datetime): void
    {
        self::assertSame($expectedTimestampHex . '0000000000000000', (string) MongoHelper::minObjectIdFor($datetime));
        self::assertSame($expectedTimestampHex . 'ffffffffffffffff', (string) MongoHelper::maxObjectIdFor($datetime));
    }

    /**
     * @return array<string, array{string, DateTimeImmutable}>
     */
    public static function objectIdBoundsProvider(): array
    {
        return [
            'utc' => ['6a7f7a00', new DateTimeImmutable('2026-08-14 20:26:40+00:00')],
            'sub-second precision is dropped' => ['6a7f7a00', new DateTimeImmutable('2026-08-14 20:26:40.999+00:00')],
            'offset is normalised to utc' => ['6a7f7a00', new DateTimeImmutable('2026-08-14 22:26:40+02:00')],
            'small timestamp is left padded' => ['0000e100', new DateTimeImmutable('1970-01-01 16:00:00+00:00')],
            'negative timestamp is clamped to zero' => ['00000000', new DateTimeImmutable('1960-01-01 00:00:00+00:00')],
            'post 2106 timestamp is clamped to max' => ['ffffffff', new DateTimeImmutable('2200-01-01 00:00:00+00:00')],
        ];
    }

    public function testSortNewestFirst(): void
    {
        $oldest = [self::DATETIME_FIELD => new UTCDateTime(1_000), 'id' => 'oldest'];
        $middle = [self::DATETIME_FIELD => new UTCDateTime(2_000), 'id' => 'middle'];
        $newest = [self::DATETIME_FIELD => new UTCDateTime(3_000), 'id' => 'newest'];
        $missing = ['id' => 'missing'];

        $sorted = MongoHelper::sortNewestFirst([$middle, $missing, $newest, $oldest], self::DATETIME_FIELD);

        self::assertSame(['newest', 'middle', 'oldest', 'missing'], array_column($sorted, 'id'));
    }
}
