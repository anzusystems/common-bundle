<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Domain\Job;

use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CommonBundle\Repository\JobRepository;
use AnzuSystems\Contracts\AnzuApp;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

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
     * @return JobInterface[]
     */
    private function getJobs(OutputInterface $output): array
    {
        do {
            $jobs = $this->jobRepo->findProcessableJobs($this->batchSize);
            if (empty($jobs)) {
                $output->writeln(
                    sprintf('No jobs found, waiting %d seconds to retry.', $this->noJobIdleTime)
                );
                sleep($this->noJobIdleTime);

                continue;
            }

            return $jobs;
        } while (false === $this->stopProcessingJobs($output));

        return [];
    }

    public function run(OutputInterface $output): void
    {
       $jobs = $this->getJobs($output);
       if (empty($jobs)) {
           return;
       }
       $progress = new ProgressBar($output, count($jobs));
       $progress->setFormat('debug');

       foreach ($jobs as $job) {
           if ($this->stopProcessingJobs($output)) {
               break;
           }
           $this->entityManager->clear();
           $this->jobProcessor->process($job);
           $progress->advance();
       }
       $progress->finish();
    }

    public function receiveSigterm(): void
    {
        $this->sigtermReceived = true;
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
        if ($this->maxExecTime< (time() - AnzuApp::getAppDate()->getTimestamp())) {
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
