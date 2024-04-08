<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Command;

use AnzuSystems\CommonBundle\Domain\Job\JobRunner;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'anzusystems:job:process',
    description: 'Process a job.',
)]
final class ProcessJobCommand extends Command implements SignalableCommandInterface
{
    public function __construct(
        private readonly JobRunner $jobRunner,
    ) {
        parent::__construct();
    }

    /**
     * @psalm-suppress UndefinedConstant - defined in pcntl extension.
     */
    public function getSubscribedSignals(): array
    {
        return [SIGTERM, SIGINT];
    }

    public function handleSignal(int $signal, false|int $previousExitCode = 0): int|false
    {
        $this->jobRunner->receiveSigterm();

        return false;
    }

    /**
     * @throws AppReadOnlyModeException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        AnzuApp::throwOnReadOnlyMode();

        $this->jobRunner->run($output);

        return self::SUCCESS;
    }
}
