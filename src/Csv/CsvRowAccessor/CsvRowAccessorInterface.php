<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Csv\CsvRowAccessor;

use SplFileObject;

interface CsvRowAccessorInterface
{
    public function setHeader(SplFileObject $csv): self;

    public function setRow(array $row): self;

    public function isInvalid(): bool;

    public function getId(): int;
}
