<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DataFixtures;

use AnzuSystems\CommonBundle\DataFixtures\Interfaces\FixturesInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

final class FixturesLoader
{
    /**
     * @var iterable<string, FixturesInterface>
     */
    private iterable $fixtures;

    public function __construct(
        iterable $fixtures,
        private readonly string $env,
    ) {
        $this->fixtures = $fixtures;
    }

    public function load(OutputInterface $output): void
    {
        foreach ($this->fixtures as $fixtures) {
            if (false === in_array($this->env, $fixtures->getEnvironments(), true)) {
                continue;
            }
            if ($fixtures->useCustomId()) {
                $fixtures->configureAssignedGenerator();
            }

            $output->writeln(PHP_EOL . 'Loading ' . $fixtures::getIndexKey());

            $progressBar = new ProgressBar($output);
            $progressBar->setFormat('debug');

            $fixtures->load($progressBar);

            if ($fixtures->useCustomId()) {
                $fixtures->disableAssignedGenerator();
            }
        }

        $output->writeln(PHP_EOL);
    }
}
