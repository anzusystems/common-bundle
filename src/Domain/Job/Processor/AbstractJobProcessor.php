<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Domain\Job\Processor;

use AnzuSystems\CommonBundle\Domain\Job\JobManager;
use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CommonBundle\Event\JobCompletedEvent;
use AnzuSystems\CommonBundle\Event\JobErrorEvent;
use AnzuSystems\CommonBundle\Event\JobEvents;
use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\String\UnicodeString;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;

abstract class AbstractJobProcessor implements JobProcessorInterface
{
    protected ManagerRegistry $doctrine;
    protected EntityManagerInterface $entityManager;
    protected CurrentAnzuUserProvider $currentAnzuUserProvider;
    protected JobManager $jobManager;
    protected EventDispatcherInterface $dispatcher;

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

    #[Required]
    public function setDispatcher(EventDispatcherInterface $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
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

        $this->dispatcher->dispatch(new JobCompletedEvent($job), JobEvents::COMPLETED);
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

    protected function finishFail(JobInterface $job, string|Throwable $error): void
    {
        if (is_string($error)) {
            @trigger_error('Support for passing string to `AbstractJobProcessor::finishFail()` second argument is deprecated. Specify Throwable object instead.', E_USER_DEPRECATED);
            $errorMessage = $error;
            $error = new \Exception($errorMessage);
        }

        if (false === $this->entityManager->isOpen()) {
            /** @var EntityManagerInterface $entityManager */
            $entityManager = $this->doctrine->resetManager();
            $this->entityManager = $entityManager;
        }
        $this->currentAnzuUserProvider->setConsoleCurrentUser();
        $this->getManagedJob($job)
            ->setResult((new UnicodeString($error->getMessage()))->truncate(255)->toString())
            ->setFinishedAt(new DateTimeImmutable())
            ->setStatus(JobStatus::Error)
        ;
        $this->jobManager->update($job);

        $this->dispatcher->dispatch(new JobErrorEvent($job, $error), JobEvents::ERROR);
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
