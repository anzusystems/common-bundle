<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Csv\CsvRowAccessor;

use SplFileObject;

abstract class AbstractCsvRowAccessor implements CsvRowAccessorInterface
{
    protected const string ID = 'id';
    protected const array HEADERS = [self::ID];

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
        $csv->seek(0);
        $index = 0;
        foreach ((array) $csv->fgetcsv() as $header) {
            if (in_array($header, static::HEADERS, true)) {
                $this->indexMap[(string) $header] = $index;
                $this->lastIndex = $index;
            }
            ++$index;
        }

        return $this;
    }

    public function setRow(array $row): self
    {
        $this->row = $row;

        return $this;
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
}
