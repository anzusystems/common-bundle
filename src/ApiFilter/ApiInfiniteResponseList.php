<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\ApiFilter;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

/**
 * @template T
 */
final class ApiInfiniteResponseList
{
    #[Serialize]
    private bool $hasNextPage = false;

    /**
     * @var array<int|string, T>
     */
    #[Serialize]
    private array $data = [];

    /**
     * @deprecated
     */
    #[Serialize]
    private int $totalCount = 0;

    public function isHasNextPage(): bool
    {
        return $this->hasNextPage;
    }

    public function setHasNextPage(bool $hasNextPage): self
    {
        $this->hasNextPage = $hasNextPage;

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
    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    /**
     * @deprecated
     */
    public function setTotalCount(int $totalCount): self
    {
        $this->totalCount = $totalCount;

        return $this;
    }
}
