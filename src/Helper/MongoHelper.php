<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Helper;

use DateTimeImmutable;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

final class MongoHelper
{
    public const string FIELD_ID = '_id';
    public const int SORT_DESC = -1;

    private const int TIMESTAMP_MIN = 0;
    private const int TIMESTAMP_MAX = 0xFFFFFFFF;
    private const string TIMESTAMP_HEX_FORMAT = '%08x';
    private const string OBJECT_ID_MIN_SUFFIX = '0000000000000000';
    private const string OBJECT_ID_MAX_SUFFIX = 'ffffffffffffffff';
    private const int DATETIME_MILLIS_FALLBACK = 0;

    public static function minObjectIdFor(DateTimeImmutable $datetime): ObjectId
    {
        return new ObjectId(self::timestampHex($datetime) . self::OBJECT_ID_MIN_SUFFIX);
    }

    public static function maxObjectIdFor(DateTimeImmutable $datetime): ObjectId
    {
        return new ObjectId(self::timestampHex($datetime) . self::OBJECT_ID_MAX_SUFFIX);
    }

    /**
     * @param list<array<string, mixed>> $documents
     *
     * @return list<array<string, mixed>>
     */
    public static function sortNewestFirst(array $documents, string $datetimeField): array
    {
        usort(
            $documents,
            static fn (array $a, array $b): int => self::datetimeMillis($b, $datetimeField) <=> self::datetimeMillis($a, $datetimeField),
        );

        return $documents;
    }

    private static function timestampHex(DateTimeImmutable $datetime): string
    {
        return sprintf(
            self::TIMESTAMP_HEX_FORMAT,
            min(self::TIMESTAMP_MAX, max(self::TIMESTAMP_MIN, $datetime->getTimestamp())),
        );
    }

    /**
     * @param array<string, mixed> $document
     */
    private static function datetimeMillis(array $document, string $datetimeField): int
    {
        $datetime = $document[$datetimeField] ?? null;
        if ($datetime instanceof UTCDateTime) {
            return (int) (string) $datetime;
        }

        return self::DATETIME_MILLIS_FALLBACK;
    }
}
