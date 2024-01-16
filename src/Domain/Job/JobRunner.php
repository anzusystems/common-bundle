<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Domain\Job;

use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CommonBundle\Repository\JobRepository;
use AnzuSystems\Contracts\AnzuApp;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

final class JobRunner
{
    const BATCH_SIZE = 50;
    const MAX_TIME = 50;
    const MAX_MEMORY = 100_000_000;
    const NO_JOB_IDLE_TIME = 10;

    private bool $sigtermReceived = false;

    public function __construct(
        private readonly JobRepository $jobRepo,
        private readonly JobProcessor $jobProcessor,
    ) {
    }

    /**
     * @return JobInterface[]
     */
    private function getJobs(OutputInterface $output): array
    {
        do {
            $jobs = $this->jobRepo->findProcessableJobs(self::BATCH_SIZE);
            if (empty($jobs)) {
                $output->writeln(
                    sprintf('No jobs found, waiting %d seconds to retry.', self::NO_JOB_IDLE_TIME)
                );
                sleep(self::NO_JOB_IDLE_TIME);

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
        if (self::MAX_MEMORY < memory_get_usage(true)) {
            $output->writeln('Max memory reached.');

            return true;
        }
        if (self::MAX_TIME < (time() - AnzuApp::getAppDate()->getTimestamp())) {
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
