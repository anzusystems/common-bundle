<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Domain\User;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CommonBundle\Model\User\UserDto;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\Contracts\Model\User\UserDto as DeprecatedUserDto;

abstract class AbstractUserManager extends AbstractManager
{
    public function createAnzuUser(AnzuUser $user, DeprecatedUserDto|UserDto $userDto, bool $flush = true): AnzuUser
    {
        $user->setId($userDto->getId());
        $this->updateAnzuUser($user, $userDto, false);
        $this->trackCreation($user);
        $this->entityManager->persist($user);
        $this->flush($flush);

        return $user;
    }

    public function updateAnzuUser(AnzuUser $user, DeprecatedUserDto|UserDto $userDto, bool $flush = true): AnzuUser
    {
        $user
            ->setEmail($userDto->getEmail())
            ->setEnabled($userDto->isEnabled())
            ->setRoles($userDto->getRoles())
            ->setPermissions($userDto->getPermissions())
        ;
        $this->colUpdate($user->getPermissionGroups(), $userDto->getPermissionGroups());
        $this->trackModification($user);
        $this->flush($flush);

        return $user;
    }
}
