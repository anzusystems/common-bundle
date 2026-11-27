<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Security;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Model\User\UserDto;
use AnzuSystems\Contracts\Entity\AnzuPermissionGroup;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\Contracts\Security\Grant;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * An editor who is not a super admin gives a user or a permission group only the roles and grants the editor holds.
 * Only changed values are compared, so the editor can still change other fields of a user with more rights.
 */
final readonly class EditorGrantGuard
{
    public function __construct(
        private Security $security,
        private CurrentAnzuUserProvider $currentAnzuUserProvider,
    ) {
    }

    /**
     * @throws AccessDeniedException
     */
    public function assertUserWrite(?AnzuUser $user, UserDto $userDto): void
    {
        if ($this->isSuperAdmin()) {
            return;
        }
        if ($user instanceof AnzuUser && in_array(needle: AnzuUser::ROLE_SUPER_ADMIN, haystack: $user->getRoles(), strict: true)) {
            throw new AccessDeniedException('Only a super admin changes a super admin.');
        }
        $this->assertChangedRolesHeld($user?->getRoles() ?? [], $userDto->getRoles());
        $this->assertRaisedGrantsHeld($user?->getPermissions() ?? [], $userDto->getPermissions());
        foreach ($userDto->getPermissionGroups() as $permissionGroup) {
            if (true === $user?->getPermissionGroups()->contains($permissionGroup)) {
                continue;
            }
            $this->assertRaisedGrantsHeld([], $permissionGroup->getPermissions());
        }
    }

    /**
     * @throws AccessDeniedException
     */
    public function assertPermissionGroupWrite(?AnzuPermissionGroup $permissionGroup, AnzuPermissionGroup $newPermissionGroup): void
    {
        if ($this->isSuperAdmin()) {
            return;
        }
        $this->assertRaisedGrantsHeld($permissionGroup?->getPermissions() ?? [], $newPermissionGroup->getPermissions());
    }

    private function isSuperAdmin(): bool
    {
        return $this->security->isGranted(AnzuUser::ROLE_SUPER_ADMIN);
    }

    /**
     * @param list<string> $currentRoles
     * @param list<string> $newRoles
     */
    private function assertChangedRolesHeld(array $currentRoles, array $newRoles): void
    {
        $changedRoles = array_merge(array_diff($newRoles, $currentRoles), array_diff($currentRoles, $newRoles));
        foreach ($changedRoles as $role) {
            if (false === $this->security->isGranted($role)) {
                throw new AccessDeniedException(sprintf('The editor does not hold the role "%s".', $role));
            }
        }
    }

    /**
     * @param array<string, int> $currentGrants
     * @param array<string, int> $newGrants
     */
    private function assertRaisedGrantsHeld(array $currentGrants, array $newGrants): void
    {
        $editorGrants = $this->currentAnzuUserProvider->getCurrentUser()->getResolvedPermissions();
        foreach ($newGrants as $permission => $grant) {
            $raised = $grant > ($currentGrants[$permission] ?? Grant::DENY);
            if ($raised && $grant > ($editorGrants[$permission] ?? Grant::DENY)) {
                throw new AccessDeniedException(sprintf('The editor does not hold the permission "%s" at this level.', $permission));
            }
        }
    }
}
