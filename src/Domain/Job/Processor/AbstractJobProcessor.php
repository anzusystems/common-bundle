<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Domain\Job\Processor;

use AnzuSystems\CommonBundle\Domain\Job\JobManager;
use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
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
    protected CurrentAnzuUserProvider $currentAnzuUserProvider;
    protected JobManager $jobManager;

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

    #[Required]
    public function setCurrentAnzuUserProvider(CurrentAnzuUserProvider $currentAnzuUserProvider): void
    {
        $this->currentAnzuUserProvider = $currentAnzuUserProvider;
    }

    #[Required]
    public function setJobManager(JobManager $jobManager): void
    {
        $this->jobManager = $jobManager;
    }

    protected function start(JobInterface $job): void
    {
        $this->currentAnzuUserProvider->setConsoleCurrentUser();
        $this->getManagedJob($job)
            ->setStartedAt(new DateTimeImmutable())
            ->setStatus(JobStatus::Processing)
        ;
        $this->jobManager->update($job);
        $this->currentAnzuUserProvider->setCurrentUser($job->getCreatedBy());
    }

    protected function finishSuccess(JobInterface $job): void
    {
        $this->currentAnzuUserProvider->setConsoleCurrentUser();
        $this->getManagedJob($job)
            ->setFinishedAt(new DateTimeImmutable())
            ->setStatus(JobStatus::Done)
        ;
        $this->jobManager->update($job);
    }

    protected function toAwaitingBatchProcess(JobInterface $job, string $lastProcessedRecord = ''): void
    {
        $this->currentAnzuUserProvider->setConsoleCurrentUser();
        $this->getManagedJob($job)
            ->setStatus(JobStatus::AwaitingBatchProcess)
            ->setLastBatchProcessedRecord($lastProcessedRecord)
            ->increaseBatchProcessedIterationCount()
        ;
        $this->jobManager->update($job);
    }

    protected function finishFail(JobInterface $job, string $error): void
    {
        if (false === $this->entityManager->isOpen()) {
            /** @var EntityManagerInterface $entityManager */
            $entityManager = $this->doctrine->resetManager();
            $this->entityManager = $entityManager;
        }
        $this->currentAnzuUserProvider->setConsoleCurrentUser();
        $this->getManagedJob($job)
            ->setResult($error)
            ->setFinishedAt(new DateTimeImmutable())
            ->setStatus(JobStatus::Error)
        ;
        $this->jobManager->update($job);
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
