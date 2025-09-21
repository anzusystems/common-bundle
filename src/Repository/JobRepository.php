<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Repository;

use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\Contracts\AnzuApp;
use Doctrine\Common\Collections\Order;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;

/**
 * @extends AbstractAnzuRepository<Job>
 */
final class JobRepository extends AbstractAnzuRepository
{
    /**
     * @return list<int>
     *
     * @throws Exception
     */
    public function findProcessableJobIds(int $maxResults): array
    {
        // First try to find jobs with AwaitingBatchProcess status
        $ids = $this->findJobIdsByStatus(JobStatus::AwaitingBatchProcess, $maxResults);

        // If no AwaitingBatchProcess jobs found, search for Waiting jobs
        if (empty($ids)) {
            $ids = $this->findJobIdsByStatus(JobStatus::Waiting, $maxResults);
        }

        return $ids;
    }

    /**
     * @return list<int>
     *
     * @throws Exception
     */
    private function findJobIdsByStatus(JobStatus $status, int $maxResults): array
    {
        return $this->getEntityManager()->getConnection()
            ->createQueryBuilder()
            ->select('job.id')
            ->from('job')
            ->where('job.status = :status')
            ->andWhere('job.scheduled_at <= :scheduledAt')
            ->setParameter('status', $status->toString())
            ->setParameter('scheduledAt', AnzuApp::getAppDate(), Types::DATETIME_IMMUTABLE)
            ->orderBy('job.priority', Order::Descending->value)
            ->addOrderBy('job.scheduled_at', Order::Ascending->value)
            ->setMaxResults($maxResults)
            ->executeQuery()
            ->fetchFirstColumn()
        ;
    }

    protected function getEntityClass(): string
    {
        return Job::class;
    }
}
