<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Helper;

use DateTimeImmutable;
use MongoDB\BSON\ObjectId;

final class MongoHelper
{
    private const int TIMESTAMP_MIN = 0;
    private const int TIMESTAMP_MAX = 0xFFFFFFFF;
    private const int TIMESTAMP_HEX_LENGTH = 8;
    private const string TIMESTAMP_HEX_PAD = '0';
    private const string OBJECT_ID_MIN_SUFFIX = '0000000000000000';
    private const string OBJECT_ID_MAX_SUFFIX = 'ffffffffffffffff';

    public static function minObjectIdFor(DateTimeImmutable $datetime): ObjectId
    {
        return new ObjectId(self::timestampHex($datetime) . self::OBJECT_ID_MIN_SUFFIX);
    }

    public static function maxObjectIdFor(DateTimeImmutable $datetime): ObjectId
    {
        return new ObjectId(self::timestampHex($datetime) . self::OBJECT_ID_MAX_SUFFIX);
    }

    private static function timestampHex(DateTimeImmutable $datetime): string
    {
        return str_pad(
            dechex(min(self::TIMESTAMP_MAX, max(self::TIMESTAMP_MIN, $datetime->getTimestamp()))),
            self::TIMESTAMP_HEX_LENGTH,
            self::TIMESTAMP_HEX_PAD,
            STR_PAD_LEFT,
        );
    }
}
