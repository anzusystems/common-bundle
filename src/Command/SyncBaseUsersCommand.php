<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Command;

use AnzuSystems\CommonBundle\Domain\User\UserSyncFacade;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Model\User\UserDto;
use AnzuSystems\SerializerBundle\Serializer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'anzusystems:user:sync-base',
    description: 'Sync base users'
)]
final class SyncBaseUsersCommand extends Command
{
    public function __construct(
        private readonly string $usersData,
        private readonly Serializer $serializer,
        private readonly UserSyncFacade $userFacade,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (empty($this->usersData)) {
            $output->writeln('Users data contains empty string');

            return Command::SUCCESS;
        }

        /** @var UserDto[] $users */
        $users = $this->serializer->deserializeIterable($this->usersData, UserDto::class, []);

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

        $output->writeln(PHP_EOL . 'Done');

        return Command::SUCCESS;
    }
}
