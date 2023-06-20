<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Csv;

use SplFileObject;

final class CsvHelper
{
    /**
     * Get total count efficiently.
     */
    public static function getTotalCount(SplFileObject $csv): int
    {
        $csv->seek(PHP_INT_MAX);
        $count = $csv->key();
        $csv->rewind();

        return $count - 1;
    }

    public static function getCsv(string $filename, ?string $mode = null ): SplFileObject
    {
        $userCsv = new SplFileObject($filename, $mode);
        $userCsv->setFlags(SplFileObject::READ_CSV);
        $userCsv->setCsvControl();

        return $userCsv;
    }

    public static function writeCsv(string $filename): SplFileObject
    {
        return self::getCsv($filename, 'w');
    }

    public static function appendCsv(string $filename): SplFileObject
    {
        return self::getCsv($filename, 'a');
    }
}
