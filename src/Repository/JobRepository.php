<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Repository;

use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use DateTimeImmutable;
use Doctrine\Common\Collections\Order;
use Doctrine\DBAL\Types\Types;

/**
 * @extends AbstractAnzuRepository<Job>
 */
final class JobRepository extends AbstractAnzuRepository
{
    public function findProcessableJob(): ?Job
    {
        return $this->findJobByStatuses(JobStatus::PROCESSABLE_STATUSES);
    }

    protected function getEntityClass(): string
    {
        return Job::class;
    }

    /**
     * @param JobStatus[] $statuses
     */
    private function findJobByStatuses(array $statuses): ?Job
    {
        $statusStrings = array_map(fn(JobStatus $status) => $status->toString(), $statuses);

        return $this
            ->createQueryBuilder('job')
            ->where('job.status IN (:statuses)')
            ->andWhere('job.scheduledAt <= :scheduledAt')
            ->setParameter('statuses', $statusStrings)
            ->setParameter('scheduledAt', new DateTimeImmutable(), Types::DATETIME_IMMUTABLE)
            ->orderBy('job.priority', Order::Descending->value)
            ->addOrderBy('job.scheduledAt', Order::Ascending->value)
            ->addOrderBy('job.id', Order::Ascending->value)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
