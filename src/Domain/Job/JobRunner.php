<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Domain\Job;

use AnzuSystems\CommonBundle\Repository\JobRepository;
use AnzuSystems\Contracts\AnzuApp;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

final class JobRunner
{
    const BATCH_SIZE = 50;
    const MAX_TIME = 50;
    const MAX_MEMORY = 100_000_000;

    private bool $sigtermReceived = false;

    public function __construct(
        private readonly JobRepository $jobRepo,
        private readonly JobProcessor $jobProcessor,
    ) {
    }

    public function run(OutputInterface $output): void
    {
       $jobs = $this->jobRepo->findProcessableJobs(self::BATCH_SIZE);
       $progress = new ProgressBar($output, self::BATCH_SIZE);
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
