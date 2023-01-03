<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Domain\PermissionGroup;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Validator;
use AnzuSystems\Contracts\Entity\AnzuPermissionGroup;

/**
 * Complete PermissionGroup processing.
 */
final class PermissionGroupFacade
{
    public function __construct(
        private readonly Validator $validator,
        private readonly PermissionGroupManager $permissionGroupManager,
    ) {
    }

    /**
     * Process new PermissionGroup creation.
     *
     * @throws ValidationException
     */
    public function create(AnzuPermissionGroup $permissionGroup): AnzuPermissionGroup
    {
        $this->validator->validateIdentifiable($permissionGroup);
        $this->permissionGroupManager->create($permissionGroup);

        return $permissionGroup;
    }

    /**
     * Process updating of PermissionGroup.
     *
     * @throws ValidationException
     */
    public function update(
        AnzuPermissionGroup $permissionGroup,
        AnzuPermissionGroup $newPermissionGroup
    ): AnzuPermissionGroup {
        $this->validator->validateIdentifiable($newPermissionGroup, $permissionGroup);
        $this->permissionGroupManager->update($permissionGroup, $newPermissionGroup);

        return $permissionGroup;
    }

    /**
     * Process deletion of PermissionGroup.
     */
    public function delete(AnzuPermissionGroup $permissionGroup): bool
    {
        return $this->permissionGroupManager->delete($permissionGroup);
    }
}
