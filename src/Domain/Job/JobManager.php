<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Domain\Job;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;

/**
 * Job persistence management.
 */
class JobManager extends AbstractManager
{
    /**
     * Persist new job.
     */
    public function create(JobInterface $job, bool $flush = true): JobInterface
    {
        $this->trackCreation($job);
        $this->entityManager->persist($job);
        $this->flush($flush);

        return $job;
    }

    /**
     * Persist new job.
     */
    public function update(JobInterface $job, bool $flush = true): JobInterface
    {
        $this->trackModification($job);
        $this->flush($flush);

        return $job;
    }

    /**
     * Delete job from persistence.
     */
    public function delete(JobInterface $job, bool $flush = true): bool
    {
        $this->entityManager->remove($job);
        $this->flush($flush);

        return true;
    }
}
