<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Repository;

use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CommonBundle\Model\Enum\JobStatus;

/**
 * @extends AbstractAnzuRepository<Job>
 */
final class JobRepository extends AbstractAnzuRepository
{
    public function findOneProcessableJob(): ?JobInterface
    {
        return $this->findOneBy([
            'status' => JobStatus::PROCESSABLE_STATUSES,
        ]);
    }

    protected function getEntityClass(): string
    {
        return Job::class;
    }
}
