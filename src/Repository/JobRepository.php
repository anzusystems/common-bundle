<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Repository;

use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\Contracts\AnzuApp;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\Query\Parameter;

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
            ->where('job.status in (:processableStatuses) AND job.scheduledAt <= :scheduledAt')
            ->setParameters(new ArrayCollection([
                new Parameter('processableStatuses', JobStatus::PROCESSABLE_STATUSES),
                new Parameter('scheduledAt', AnzuApp::getAppDate()),
                'scheduledAt' => AnzuApp::getAppDate(),
            ]))
            ->orderBy('job.priority', Order::Descending->value)
            ->addOrderBy('job.scheduledAt', Order::Ascending->value)
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
