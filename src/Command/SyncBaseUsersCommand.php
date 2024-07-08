<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Command;

use AnzuSystems\CommonBundle\Domain\User\UserSyncFacade;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Model\User\UserDto;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\SerializerBundle\Serializer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'anzusystems:user:sync-base',
    description: 'Sync base users'
)]
final class SyncBaseUsersCommand extends Command
{
    private const string USERS_FILE_PATH_OPT = 'file';
    private const string USERS_FILE_PATH_DEFAULT = 'users.json';

    public function __construct(
        private readonly Serializer $serializer,
        private readonly UserSyncFacade $userFacade,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        $this
            ->addOption(
                name: self::USERS_FILE_PATH_OPT,
                mode: InputOption::VALUE_REQUIRED,
                default: AnzuApp::getDataDir() . '/' . self::USERS_FILE_PATH_DEFAULT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getOption(self::USERS_FILE_PATH_OPT);

        if (false === file_exists($filePath)) {
            $output->writeln("<error>File not found at path: ({$filePath})</error>");

            return Command::FAILURE;
        }

        $contents = file_get_contents($filePath);
        if (false === json_validate($contents)) {
            $output->writeln("<error>Invalid json content at path: ({$filePath})</error>");

            return Command::FAILURE;
        }

        /** @var UserDto[] $users */
        $users = $this->serializer->deserializeIterable($contents, UserDto::class, []);

        foreach ($users as $userDto) {
            $output->writeln($userDto->getId() . ' ' . $userDto->getEmail());

            try {
                $this->userFacade->upsertUser($userDto);
            } catch (ValidationException $validationException) {
                $output->writeln(
                    sprintf(
                        PHP_EOL . '<comment>Validation error has occurred: (%s) for user (%d, %s)</comment>',
                        json_encode($validationException->getFormattedErrors()),
                        (int) $userDto->getId(),
                        $userDto->getEmail(),
                    )
                );
            } catch (Throwable $e) {
                $output->writeln(
                    sprintf(
                        PHP_EOL . '<comment>An error has occurred: (%s) for user (%d, %s)</comment>',
                        $e->getMessage(),
                        (int) $userDto->getId(),
                        $userDto->getEmail(),
                    )
                );
            }
        }

        $output->writeln('<info>Done</info>');

        return Command::SUCCESS;
    }
}
