<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Repository;

use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\Contracts\AnzuApp;
use Doctrine\Common\Collections\Order;
use Doctrine\DBAL\Types\Types;

/**
 * @extends AbstractAnzuRepository<Job>
 */
final class JobRepository extends AbstractAnzuRepository
{
    public function findProcessableJob(): ?Job
    {
        $id = $this->findJobByStatus(JobStatus::AwaitingBatchProcess);
        if ($id) {
            return $id;
        }

        return $this->findJobByStatus(JobStatus::Waiting);
    }

    private function findJobByStatus(JobStatus $status): ?Job
    {
        return $this
            ->createQueryBuilder('job')
            ->where('job.status = :status')
            ->andWhere('job.scheduled_at <= :scheduledAt')
            ->setParameter('status', $status->toString())
            ->setParameter('scheduledAt', AnzuApp::getAppDate(), Types::DATETIME_IMMUTABLE)
            ->orderBy('job.priority', Order::Descending->value)
            ->addOrderBy('job.scheduled_at', Order::Ascending->value)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    protected function getEntityClass(): string
    {
        return Job::class;
    }
}
