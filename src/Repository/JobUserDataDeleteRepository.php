<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Repository;

use AnzuSystems\CommonBundle\Entity\JobUserDataDelete;

/**
 * @extends AbstractAnzuRepository<JobUserDataDelete>
 */
final class JobUserDataDeleteRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return JobUserDataDelete::class;
    }
}
