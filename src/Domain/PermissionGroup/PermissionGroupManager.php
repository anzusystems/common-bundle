<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Domain\PermissionGroup;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\Contracts\Entity\AnzuPermissionGroup;

/**
 * PermissionGroup persistence management.
 */
final class PermissionGroupManager extends AbstractManager
{
    /**
     * Persist new PermissionGroup.
     */
    public function create(AnzuPermissionGroup $permissionGroup, bool $flush = true): AnzuPermissionGroup
    {
        $this->trackCreation($permissionGroup);
        $this->entityManager->persist($permissionGroup);
        $this->flush($flush);

        return $permissionGroup;
    }

    /**
     * Update PermissionGroup and persist it.
     */
    public function update(
        AnzuPermissionGroup $permissionGroup,
        AnzuPermissionGroup $newPermissionGroup,
        bool $flush = true,
    ): AnzuPermissionGroup {
        $this->trackModification($permissionGroup);
        $permissionGroup->setPermissions($newPermissionGroup->getPermissions());
        $permissionGroup->setTitle($newPermissionGroup->getTitle());
        $permissionGroup->setDescription($newPermissionGroup->getDescription());

        $this->flush($flush);

        return $permissionGroup;
    }

    /**
     * Delete PermissionGroup from persistence.
     */
    public function delete(AnzuPermissionGroup $permissionGroup, bool $flush = true): bool
    {
        $this->entityManager->remove($permissionGroup);
        $this->flush($flush);

        return true;
    }
}
