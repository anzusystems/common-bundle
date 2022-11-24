<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Repository;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CommonBundle\ApiFilter\CustomFilterInterface;
use Doctrine\Common\Collections\Collection;

interface AnzuRepositoryInterface
{
    public function getAllById(int | string ...$ids): Collection;

    public function getAllByIdIndexed(int | string ...$id): Collection;

    public function findByApiParams(ApiParams $apiParams, ?CustomFilterInterface $customFilter = null): ApiResponseList;

    /**
     * Use this instead of findByApiParams if your table is too big.
     * It returns infinite response list without performing any count.
     */
    public function findByApiParamsWithInfiniteListing(
        ApiParams $apiParams,
        ?CustomFilterInterface $customFilter = null,
    ): ApiInfiniteResponseList;

    public function exists(int | string $id): bool;
}
