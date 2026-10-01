<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Csv;

use AnzuSystems\CommonBundle\Csv\CsvHelper;
use PHPUnit\Framework\TestCase;

final class CsvHelperTest extends TestCase
{
    private string $filename = '';

    protected function setUp(): void
    {
        $this->filename = (string) tempnam(sys_get_temp_dir(), 'csv-helper-');
    }

    protected function tearDown(): void
    {
        unlink($this->filename);
    }

    public function testGetCsvKeepsTheDefaultControlWithoutDeprecation(): void
    {
        $deprecations = [];
        set_error_handler(
            static function (int $level, string $message) use (&$deprecations): bool {
                $deprecations[] = $message;

                return true;
            },
            E_DEPRECATED
        );

        try {
            $csv = CsvHelper::getCsv($this->filename);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $deprecations);
        $this->assertSame([',', '"', '\\'], $csv->getCsvControl());
    }

    public function testGetCsvReadsWithTheGivenControl(): void
    {
        file_put_contents($this->filename, "id;name\r\n1;\"Banská Bystrica; Zvolen\"\r\n");

        $csv = CsvHelper::getCsv(filename: $this->filename, separator: ';', escape: '');

        $this->assertSame(['id', 'name'], $csv->fgetcsv());
        $this->assertSame(['1', 'Banská Bystrica; Zvolen'], $csv->fgetcsv());
    }
}
