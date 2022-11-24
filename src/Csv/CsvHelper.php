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

    public static function getCsv(string $filename): SplFileObject
    {
        $userCsv = new SplFileObject($filename);
        $userCsv->setFlags(SplFileObject::READ_CSV);
        $userCsv->setCsvControl();

        return $userCsv;
    }

    public static function writeCsv(string $filename): SplFileObject
    {
        $userCsv = new SplFileObject($filename, 'w');
        $userCsv->setFlags(SplFileObject::READ_CSV);
        $userCsv->setCsvControl();

        return $userCsv;
    }
}
