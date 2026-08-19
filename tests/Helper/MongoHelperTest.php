<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Helper;

use AnzuSystems\CommonBundle\Helper\MongoHelper;
use AnzuSystems\CommonBundle\Tests\AnzuKernelTestCase;
use DateTimeImmutable;

final class MongoHelperTest extends AnzuKernelTestCase
{
    /**
     * @dataProvider minObjectIdProvider
     */
    public function testMinObjectIdFor(string $expectedResult, DateTimeImmutable $datetime): void
    {
        self::assertSame($expectedResult, (string) MongoHelper::minObjectIdFor($datetime));
    }

    /**
     * @dataProvider maxObjectIdProvider
     */
    public function testMaxObjectIdFor(string $expectedResult, DateTimeImmutable $datetime): void
    {
        self::assertSame($expectedResult, (string) MongoHelper::maxObjectIdFor($datetime));
    }

    /**
     * @return list<array{string, DateTimeImmutable}>
     */
    public function minObjectIdProvider(): array
    {
        return [
            ['6a7f7a000000000000000000', new DateTimeImmutable('2026-08-14 20:26:40+00:00')],
            ['0000e1000000000000000000', new DateTimeImmutable('1970-01-01 16:00:00+00:00')],
            ['000000000000000000000000', new DateTimeImmutable('1960-01-01 00:00:00+00:00')],
            ['ffffffff0000000000000000', new DateTimeImmutable('2200-01-01 00:00:00+00:00')],
        ];
    }

    /**
     * @return list<array{string, DateTimeImmutable}>
     */
    public function maxObjectIdProvider(): array
    {
        return [
            ['6a7f7a00ffffffffffffffff', new DateTimeImmutable('2026-08-14 20:26:40+00:00')],
            ['00000000ffffffffffffffff', new DateTimeImmutable('1960-01-01 00:00:00+00:00')],
            ['ffffffffffffffffffffffff', new DateTimeImmutable('2200-01-01 00:00:00+00:00')],
        ];
    }
}
