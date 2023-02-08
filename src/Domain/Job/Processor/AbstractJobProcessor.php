<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Domain\Job\Processor;

use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractJobProcessor implements JobProcessorInterface
{
    protected ManagerRegistry $doctrine;
    protected EntityManagerInterface $entityManager;

    #[Required]
    public function setManagerRegistry(ManagerRegistry $doctrine): void
    {
        $this->doctrine = $doctrine;
    }

    #[Required]
    public function setEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->entityManager = $entityManager;
    }

    protected function start(JobInterface $job): void
    {
        $this->getManagedJob($job)
            ->setStartedAt(new DateTimeImmutable())
            ->setStatus(JobStatus::Processing)
        ;
        $this->entityManager->flush();
    }

    protected function finishSuccess(JobInterface $job): void
    {
        $this->getManagedJob($job)
            ->setFinishedAt(new DateTimeImmutable())
            ->setStatus(JobStatus::Done)
        ;
        $this->entityManager->flush();
    }

    protected function toWaitingBatch(JobInterface $job): void
    {
        $this->getManagedJob($job)
            ->setStatus(JobStatus::WaitingBatch);
        $this->entityManager->flush();
    }

    protected function finishFail(JobInterface $job, string $error): void
    {
        if (false === $this->entityManager->isOpen()) {
            /** @var EntityManagerInterface $entityManager */
            $entityManager = $this->doctrine->resetManager();
            $this->entityManager = $entityManager;
        }

        $this->getManagedJob($job)
            ->setResult($error)
            ->setFinishedAt(new DateTimeImmutable())
            ->setStatus(JobStatus::Error)
        ;
        $this->entityManager->flush();
    }

    protected function getManagedJob(JobInterface $job): JobInterface
    {
        if (false === $this->entityManager->contains($job)) {
            /** @var JobInterface $job */
            $job = $this->entityManager->find($job::class, $job);
        }

        return $job;
    }
}
