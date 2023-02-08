<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Command;

use AnzuSystems\CommonBundle\Domain\Job\JobProcessor;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'anzusystems:job:process',
    description: 'Process a job.',
)]
final class ProcessJobCommand extends Command
{
    public function __construct(
        private readonly JobProcessor $jobProcessor,
    ) {
        parent::__construct();
    }

    /**
     * @throws AppReadOnlyModeException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        AnzuApp::throwOnReadOnlyMode();

        $this->jobProcessor->process();

        return self::SUCCESS;
    }
}
