<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Domain\Job;

use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\CommonBundle\Validator\Validator;
use RuntimeException;

/**
 * Complete Job processing.
 */
final class JobFacade
{
    public function __construct(
        private readonly Validator $validator,
        private readonly JobManager $manager,
    ) {
    }

    /**
     * Process new job creation.
     *
     * @throws ValidationException
     */
    public function create(JobInterface $job): JobInterface
    {
        $this->validator->validate($job);
        $this->manager->create($job);

        return $job;
    }

    /**
     * Process deletion.
     */
    public function delete(JobInterface $job): bool
    {
        if ($job->getStatus()->is(JobStatus::Processing)) {
            throw new RuntimeException('cannot_delete_job_in_progress');
        }

        return $this->manager->delete($job);
    }
}
