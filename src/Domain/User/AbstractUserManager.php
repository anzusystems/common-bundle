<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Domain\User;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CommonBundle\Model\Permission\PermissionUserUpdateDto;
use AnzuSystems\Contracts\Entity\AnzuUser;

abstract class AbstractUserManager extends AbstractManager
{
    public function updatePermissions(AnzuUser $user, PermissionUserUpdateDto $permissionUserUpdateDto): AnzuUser
    {
        $user
            ->setRoles($permissionUserUpdateDto->getRoles())
            ->setPermissions($permissionUserUpdateDto->getPermissions())
            ->setEnabled($permissionUserUpdateDto->isEnabled())
        ;
        $this->colUpdate($user->getPermissionGroups(), $permissionUserUpdateDto->getPermissionGroups());
        $this->trackModification($user);
        $this->flush();

        return $user;
    }
}
