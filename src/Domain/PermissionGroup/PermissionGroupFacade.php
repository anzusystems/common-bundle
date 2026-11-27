<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Domain\PermissionGroup;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Security\EditorGrantGuard;
use AnzuSystems\CommonBundle\Validator\Validator;
use AnzuSystems\Contracts\Entity\AnzuPermissionGroup;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Complete PermissionGroup processing.
 */
final class PermissionGroupFacade
{
    public function __construct(
        private readonly Validator $validator,
        private readonly PermissionGroupManager $permissionGroupManager,
        private readonly EditorGrantGuard $editorGrantGuard,
    ) {
    }

    /**
     * Process new PermissionGroup creation.
     *
     * @throws ValidationException
     * @throws AccessDeniedException
     */
    public function create(AnzuPermissionGroup $permissionGroup): AnzuPermissionGroup
    {
        $this->validator->validate($permissionGroup);
        $this->editorGrantGuard->assertPermissionGroupWrite(null, $permissionGroup);
        $this->permissionGroupManager->create($permissionGroup);

        return $permissionGroup;
    }

    /**
     * Process updating of PermissionGroup.
     *
     * @throws ValidationException
     * @throws AccessDeniedException
     */
    public function update(
        AnzuPermissionGroup $permissionGroup,
        AnzuPermissionGroup $newPermissionGroup
    ): AnzuPermissionGroup {
        $this->validator->validate($newPermissionGroup, $permissionGroup);
        $this->editorGrantGuard->assertPermissionGroupWrite($permissionGroup, $newPermissionGroup);
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
