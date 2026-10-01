<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Csv\CsvRowAccessor;

use SplFileObject;

abstract class AbstractCsvRowAccessor implements CsvRowAccessorInterface
{
    protected const string ID = 'id';
    protected const array HEADERS = [self::ID];

    private const string UTF8_BOM = "\u{FEFF}";

    protected array $row = [];
    protected array $indexMap = [];
    protected int $lastIndex = 0;

    /**
     * @psalm-suppress UnsafeInstantiation
     */
    public static function getInstance(SplFileObject $csv): static
    {
        return (new static())
            ->setHeader($csv)
        ;
    }

    public function setHeader(SplFileObject $csv): static
    {
        $index = 0;
        foreach ($this->readHeaders($csv) as $header) {
            if (in_array($header, static::HEADERS, true)) {
                $this->indexMap[(string) $header] = $index;
                $this->lastIndex = $index;
            }
            ++$index;
        }

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getMissingHeaders(): array
    {
        return array_values(array_diff(static::HEADERS, array_keys($this->indexMap)));
    }

    public function setRow(array $row): self
    {
        $this->row = $row;

        return $this;
    }

    /**
     * SplFileObject reads a blank line, including the one after the last line break, as [null].
     */
    public function isEmpty(): bool
    {
        return [] === $this->row || [null] === $this->row;
    }

    public function isInvalid(): bool
    {
        if (false === array_key_exists($this->lastIndex, $this->row)) {
            return true;
        }

        return $this->getId() < 1;
    }

    public function getId(): int
    {
        return (int) $this->get(static::ID);
    }

    protected function get(string $header): mixed
    {
        return $this->row[
            $this->indexMap[$header]
        ];
    }

    /**
     * The BOM is skipped before parsing: in front of an enclosure it keeps fgetcsv() from seeing a quoted first header.
     */
    private function readHeaders(SplFileObject $csv): array
    {
        $csv->rewind();
        if (self::UTF8_BOM !== $csv->fread(strlen(self::UTF8_BOM))) {
            $csv->rewind();
        }

        return (array) $csv->fgetcsv();
    }
}
