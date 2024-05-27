<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Domain\User;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CommonBundle\Model\User\BaseUserDto;
use AnzuSystems\CommonBundle\Model\User\UserDto;
use AnzuSystems\Contracts\Entity\AnzuUser;

abstract class AbstractUserManager extends AbstractManager
{
    public function createAnzuUser(AnzuUser $user, UserDto $userDto, bool $flush = true): AnzuUser
    {
        $user->setId($userDto->getId());
        $this->updateAnzuUser($user, $userDto, false);
        $this->trackCreation($user);
        $this->entityManager->persist($user);
        $this->flush($flush);

        return $user;
    }

    public function updateBaseAnzuUser(AnzuUser $user, BaseUserDto $userDto, bool $flush = true): AnzuUser
    {
        $user
            ->setEmail($userDto->getEmail())
        ;
        $user->getAvatar()
            ->setColor($userDto->getAvatar()->getColor())
            ->setText($userDto->getAvatar()->getText())
        ;
        $user->getPerson()
            ->setFirstName($userDto->getPerson()->getFirstName())
            ->setLastName($userDto->getPerson()->getLastName())
            ->setFullName($userDto->getPerson()->getFullName())
        ;
        $this->trackModification($user);
        $this->flush($flush);

        return $user;
    }

    public function updateAnzuUser(AnzuUser $user, UserDto $userDto, bool $flush = true): AnzuUser
    {
        $user
            ->setRoles($userDto->getRoles())
            ->setPermissions($userDto->getPermissions())
            ->setEnabled($userDto->isEnabled())
        ;
        $this->colUpdate($user->getPermissionGroups(), $userDto->getPermissionGroups());
        $this->updateBaseAnzuUser($user, $userDto, $flush);

        return $user;
    }
}
