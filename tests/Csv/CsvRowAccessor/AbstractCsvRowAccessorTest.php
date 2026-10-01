<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Csv\CsvRowAccessor;

use AnzuSystems\CommonBundle\Csv\CsvHelper;
use AnzuSystems\CommonBundle\Csv\CsvRowAccessor\AbstractCsvRowAccessor;
use PHPUnit\Framework\TestCase;
use SplFileObject;

final class AbstractCsvRowAccessorTest extends TestCase
{
    private string $filename = '';

    protected function setUp(): void
    {
        $this->filename = (string) tempnam(sys_get_temp_dir(), 'csv-row-accessor-');
    }

    protected function tearDown(): void
    {
        unlink($this->filename);
    }

    /**
     * @dataProvider headerProvider
     */
    public function testGetMissingHeaders(string $header, array $missingHeaders): void
    {
        file_put_contents($this->filename, $header . "\r\n5;Bratislava\r\n");

        $accessor = $this->createAccessor()
            ->setHeader($this->getCsv());

        $this->assertSame($missingHeaders, $accessor->getMissingHeaders());
    }

    public function headerProvider(): array
    {
        return [
            'all headers' => ['id;name', []],
            'all headers behind a UTF-8 BOM' => ["\u{FEFF}id;name", []],
            'header without a column' => ['id;title', ['name']],
        ];
    }

    public function testFirstColumnIsReadBehindAUtf8Bom(): void
    {
        file_put_contents($this->filename, "\u{FEFF}id;name\r\n5;Bratislava\r\n");
        $csv = $this->getCsv();
        $accessor = $this->createAccessor()
            ->setHeader($csv);

        $accessor->setRow((array) $csv->fgetcsv());

        $this->assertFalse($accessor->isInvalid());
        $this->assertSame(5, $accessor->getId());
    }

    /**
     * @dataProvider rowProvider
     */
    public function testIsEmpty(array $row, bool $isEmpty): void
    {
        $this->assertSame($isEmpty, $this->createAccessor()->setRow($row)->isEmpty());
    }

    public function rowProvider(): array
    {
        return [
            'blank line' => [[null], true],
            'no cells' => [[], true],
            'row' => [['5', 'Bratislava'], false],
            'row with an empty first cell' => [['', 'Bratislava'], false],
        ];
    }

    public function testBlankLinesAreReadAsEmptyRows(): void
    {
        file_put_contents($this->filename, "id;name\r\n\r\n5;Bratislava\r\n");
        $csv = $this->getCsv();
        $accessor = $this->createAccessor()
            ->setHeader($csv);

        $emptyRows = [];
        while (false === $csv->eof()) {
            $emptyRows[] = $accessor->setRow((array) $csv->fgetcsv())->isEmpty();
        }

        $this->assertSame([true, false, true], $emptyRows);
    }

    private function getCsv(): SplFileObject
    {
        return CsvHelper::getCsv(filename: $this->filename, separator: ';', escape: '');
    }

    private function createAccessor(): AbstractCsvRowAccessor
    {
        return new class() extends AbstractCsvRowAccessor {
            protected const array HEADERS = [self::ID, self::NAME];
            private const string NAME = 'name';
        };
    }
}
