<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Command;

use MongoDB\Database;
use MongoDB\Driver\Exception\CommandException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'anzu:mcp:create-log-collection',
    description: 'Create the capped MongoDB collection for MCP tool call logs.'
)]
final class CreateMcpLogCollectionCommand extends Command
{
    private const int BYTES_PER_MEGABYTE = 1_024 * 1_024;
    private const string CAPPED_OPTION = 'capped';
    private const int NAMESPACE_EXISTS_ERROR_CODE = 48;

    public function __construct(
        private readonly Database $mcpLogDatabase,
        private readonly string $mcpLogCollectionName,
        private readonly int $mcpLogCollectionSizeMb,
    ) {
        parent::__construct();
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $options = $this->findCollectionOptions();
        if (null === $options) {
            return $this->createCollection($io);
        }

        return $this->reportExistingCollection($io, $options);
    }

    private function createCollection(SymfonyStyle $io): int
    {
        try {
            $this->mcpLogDatabase->createCollection($this->mcpLogCollectionName, [
                self::CAPPED_OPTION => true,
                'size' => $this->mcpLogCollectionSizeMb * self::BYTES_PER_MEGABYTE,
            ]);
        } catch (CommandException $exception) {
            return $this->resolveCreateRace($io, $exception);
        }
        $io->writeln(sprintf(
            'Created capped collection "%s" (%d MB).',
            $this->mcpLogCollectionName,
            $this->mcpLogCollectionSizeMb,
        ));

        return Command::SUCCESS;
    }

    private function resolveCreateRace(SymfonyStyle $io, CommandException $exception): int
    {
        if (self::NAMESPACE_EXISTS_ERROR_CODE === $exception->getCode()) {
            return $this->reportExistingCollection($io, $this->findCollectionOptions() ?? []);
        }

        throw $exception;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function reportExistingCollection(SymfonyStyle $io, array $options): int
    {
        if (true === ($options[self::CAPPED_OPTION] ?? false)) {
            $io->writeln(sprintf('Capped collection "%s" already exists.', $this->mcpLogCollectionName));

            return Command::SUCCESS;
        }
        $io->error(sprintf('Collection "%s" exists but is not capped, convert it manually.', $this->mcpLogCollectionName));

        return Command::FAILURE;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findCollectionOptions(): ?array
    {
        foreach ($this->mcpLogDatabase->listCollections([
            'filter' => [
                'name' => $this->mcpLogCollectionName,
            ],
        ]) as $collectionInfo) {
            return $collectionInfo->getOptions();
        }

        return null;
    }
}
