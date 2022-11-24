<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\ApiFilter;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

/**
 * @template T
 */
final class ApiResponseList
{
    /**
     * @deprecated
     */
    #[Serialize]
    private bool $bigTable = false;

    #[Serialize]
    private int $totalCount = 0;

    /**
     * @var array<int|string, T>
     */
    #[Serialize]
    private array $data = [];

    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    public function setTotalCount(int $totalCount): self
    {
        $this->totalCount = $totalCount;

        return $this;
    }

    /**
     * @return array<int|string, T>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<int|string, T> $data
     */
    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @deprecated
     */
    public function isBigTable(): bool
    {
        return $this->bigTable;
    }

    /**
     * @deprecated
     */
    public function setBigTable(bool $bigTable): self
    {
        $this->bigTable = $bigTable;

        return $this;
    }
}
