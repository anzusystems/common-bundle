<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Domain\Job;

use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CommonBundle\Kernel\AnzuKernel;
use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\CommonBundle\Repository\JobRepository;
use AnzuSystems\Contracts\AnzuApp;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class JobRunner
{
    private bool $sigtermReceived = false;

    public function __construct(
        private readonly JobRepository $jobRepo,
        private readonly JobProcessor $jobProcessor,
        private readonly EntityManagerInterface $entityManager,
        private readonly int $batchSize,
        private readonly int $maxExecTime,
        private readonly int $maxMemory,
        /** @var int<0, max> */
        private readonly int $noJobIdleTime,
    ) {
    }

    /**
     * @throws Exception
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function run(OutputInterface $output): void
    {
        $progress = new ProgressBar($output);
        $progress->setFormat('debug');

        do {
            $jobIds = $this->getJobIds($output);
            foreach ($jobIds as $jobId) {
                if ($this->stopProcessingJobs($output)) {
                    break 2;
                }
                $job = $this->entityManager->find(Job::class, $jobId);
                if (null === $job || false === $job->getStatus()->in(JobStatus::PROCESSABLE_STATUSES)) {
                    $output->writeln(sprintf('<error>Job %d not found or not processable.</error>', $jobId));

                    continue;
                }
                $success = $this->jobProcessor->process($job);
                if (false === $success) {
                    $output->writeln(sprintf('<error>Job %d failed.</error>', $jobId));

                    break 2;
                }
                $this->entityManager->clear();
                $progress->advance();
            }
        } while (false === empty($jobIds));

        $progress->finish();
    }

    public function receiveSigterm(): void
    {
        $this->sigtermReceived = true;
    }

    /**
     * @return list<int>
     *
     * @throws Exception
     */
    private function getJobIds(OutputInterface $output): array
    {
        do {
            $jobIds = $this->jobRepo->findProcessableJobIds($this->batchSize);
            if (empty($jobIds)) {
                sleep($this->noJobIdleTime);

                continue;
            }

            return $jobIds;
        } while (false === $this->stopProcessingJobs($output));

        return [];
    }

    /**
     * Check thresholds for max time, memory, sigterm.
     */
    private function stopProcessingJobs(OutputInterface $output): bool
    {
        if ($this->maxMemory < memory_get_usage(true)) {
            $output->writeln('Max memory reached.');

            return true;
        }
        if ($this->maxExecTime < (time() - AnzuApp::getAppDate()->getTimestamp())) {
            $output->writeln('Max execution time reached.');

            return true;
        }
        if ($this->sigtermReceived) {
            $output->writeln('Sigterm received.');

            return true;
        }

        return false;
    }
}
