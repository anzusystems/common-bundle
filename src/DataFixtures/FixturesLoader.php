<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DataFixtures;

use AnzuSystems\CommonBundle\DataFixtures\Interfaces\FixturesInterface;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly EntityManagerInterface $entityManager,
        private readonly bool $readOnlyAfterLoad = false,
    ) {
        $this->fixtures = $fixtures;
    }

    public function load(OutputInterface $output): void
    {
        $updatedEntityClasses = $this->readOnlyAfterLoad ? $this->collectUpdatedEntityClasses() : [];
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

            if ($this->readOnlyAfterLoad) {
                $this->markLoadedEntitiesReadOnly($updatedEntityClasses);
            }

            if ($fixtures->useCustomId()) {
                $fixtures->disableAssignedGenerator();
            }
        }

        $output->writeln(PHP_EOL);
    }

    /**
     * @return list<class-string>
     */
    private function collectUpdatedEntityClasses(): array
    {
        $updatedEntityClasses = [];
        foreach ($this->fixtures as $fixtures) {
            $updatedEntityClasses = [...$updatedEntityClasses, ...$fixtures->getUpdatedEntityClasses()];
        }

        return $updatedEntityClasses;
    }

    /**
     * Every flush computes a change set for every managed entity, so fixtures that flush per entity re-check all
     * entities of the fixtures loaded before them. Doctrine skips read-only entities and does not save their changes.
     *
     * @param list<class-string> $updatedEntityClasses
     */
    private function markLoadedEntitiesReadOnly(array $updatedEntityClasses): void
    {
        $this->entityManager->flush();

        $unitOfWork = $this->entityManager->getUnitOfWork();
        foreach ($unitOfWork->getIdentityMap() as $entities) {
            foreach ($entities as $entity) {
                if (array_any($updatedEntityClasses, static fn (string $class): bool => $entity instanceof $class)) {
                    continue;
                }
                $unitOfWork->markReadOnly($entity);
            }
        }
    }
}
