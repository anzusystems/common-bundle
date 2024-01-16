<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Repository;

use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\Contracts\AnzuApp;
use Doctrine\Common\Collections\Criteria;

/**
 * @extends AbstractAnzuRepository<Job>
 */
final class JobRepository extends AbstractAnzuRepository
{
    /**
     * @return JobInterface[]
     */
    public function findProcessableJobs(int $maxResults): array
    {
        $dqb = $this->createQueryBuilder('job');
        $dqb
            ->select('job')
            ->where('status in (:processableStatuses) AND scheduledAt >= :scheduledAt')
            ->setParameters([
                'processableStatuses' => JobStatus::PROCESSABLE_STATUSES,
                'scheduledAt' => AnzuApp::getAppDate(),
            ])
            ->orderBy('priority', Criteria::DESC)
            ->addOrderBy('scheduledAt', Criteria::ASC)
            ->setMaxResults($maxResults)
        ;

        $results = $dqb->getQuery()->getResult();
        if (is_array($results)) {
            return $results;
        }

        return [];
    }

    protected function getEntityClass(): string
    {
        return Job::class;
    }
}
