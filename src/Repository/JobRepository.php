<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Repository;

use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\Contracts\AnzuApp;
use Doctrine\Common\Collections\Order;
use Doctrine\DBAL\ArrayParameterType;
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
        $ids = $this->getEntityManager()->getConnection()
            ->createQueryBuilder()
            ->select('job.id')
            ->from('job')
            ->where('job.status in (:processableStatuses)')
            ->andWhere('job.scheduled_at <= :scheduledAt')
            ->setParameter('processableStatuses', array_map(static fn (JobStatus $status) => $status->toString(), JobStatus::PROCESSABLE_STATUSES), ArrayParameterType::STRING)
            ->setParameter('scheduledAt', AnzuApp::getAppDate(), Types::DATETIME_IMMUTABLE)
            ->orderBy('job.priority', Order::Descending->value)
            ->addOrderBy('job.scheduled_at', Order::Ascending->value)
            ->setMaxResults($maxResults)
            ->executeQuery()
            ->fetchFirstColumn()
        ;

        return $ids;
    }

    protected function getEntityClass(): string
    {
        return Job::class;
    }
}
