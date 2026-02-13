<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Domain\User;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Model\User\UserDto;
use AnzuSystems\CommonBundle\Validator\Validator;
use AnzuSystems\Contracts\Entity\AnzuUser;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;

/**
 * @internal
 */
final readonly class UserSyncFacade
{
    /**
     * @param class-string<AnzuUser> $userEntityClass
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $userEntityClass,
        private Validator $validator,
        private UserSyncManager $userSyncManager,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ValidationException
     * @throws ORMException
     */
    public function upsertUser(UserDto $userDto): AnzuUser
    {
        $this->validator->validate($userDto);
        $user = $this->entityManager->find($this->userEntityClass, $userDto->getId());

        return null === $user
            ? $this->userSyncManager->createAnzuUser(new $this->userEntityClass(), $userDto)
            : $this->userSyncManager->updateAnzuUser($user, $userDto)
        ;
    }
}
