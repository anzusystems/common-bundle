<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Security;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Model\User\UserDto;
use AnzuSystems\CommonBundle\Security\EditorGrantGuard;
use AnzuSystems\CommonBundle\Tests\Data\Entity\PermissionGroup;
use AnzuSystems\CommonBundle\Tests\Data\Entity\User;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\Contracts\Security\Grant;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class EditorGrantGuardTest extends TestCase
{
    private const string USER_UPDATE = 'user_update';
    private const string ARTICLE_READ = 'article_read';
    private const string ARTICLE_DELETE = 'article_delete';

    public function testSuperAdminGrantsAnything(): void
    {
        $guard = $this->createGuard(editorRoles: [AnzuUser::ROLE_SUPER_ADMIN], editorGrants: []);

        $guard->assertUserWrite($this->createSuperAdmin(), $this->createDto(
            roles: [AnzuUser::ROLE_SUPER_ADMIN],
            grants: [self::ARTICLE_DELETE => Grant::ALLOW],
        ));
        $guard->assertPermissionGroupWrite(null, $this->createGroup([self::ARTICLE_DELETE => Grant::ALLOW]));

        $this->addToAssertionCount(2);
    }

    public function testEditorCannotGrantSuperAdmin(): void
    {
        $guard = $this->createGuard(editorRoles: [AnzuUser::ROLE_ADMIN], editorGrants: [self::USER_UPDATE => Grant::ALLOW]);

        $this->expectException(AccessDeniedException::class);
        $guard->assertUserWrite($this->createUser([AnzuUser::ROLE_ADMIN]), $this->createDto(
            roles: [AnzuUser::ROLE_ADMIN, AnzuUser::ROLE_SUPER_ADMIN],
        ));
    }

    public function testEditorCannotChangeSuperAdmin(): void
    {
        $guard = $this->createGuard(editorRoles: [AnzuUser::ROLE_ADMIN], editorGrants: [self::USER_UPDATE => Grant::ALLOW]);

        $this->expectException(AccessDeniedException::class);
        $guard->assertUserWrite($this->createSuperAdmin(), $this->createDto(
            roles: [AnzuUser::ROLE_ADMIN, AnzuUser::ROLE_SUPER_ADMIN],
        ));
    }

    public function testEditorKeepsRolesAndGrantsOfStrongerUser(): void
    {
        $guard = $this->createGuard(editorRoles: [AnzuUser::ROLE_ADMIN], editorGrants: [self::USER_UPDATE => Grant::ALLOW]);
        $user = $this->createUser([AnzuUser::ROLE_ADMIN], [self::ARTICLE_DELETE => Grant::ALLOW]);

        $guard->assertUserWrite($user, $this->createDto(
            roles: [AnzuUser::ROLE_ADMIN],
            grants: [self::ARTICLE_DELETE => Grant::ALLOW],
        ));

        $this->addToAssertionCount(1);
    }

    public function testEditorLowersGrantNotHeld(): void
    {
        $guard = $this->createGuard(editorRoles: [AnzuUser::ROLE_ADMIN], editorGrants: [self::USER_UPDATE => Grant::ALLOW]);
        $user = $this->createUser([AnzuUser::ROLE_ADMIN], [self::ARTICLE_DELETE => Grant::ALLOW]);

        $guard->assertUserWrite($user, $this->createDto(
            roles: [AnzuUser::ROLE_ADMIN],
            grants: [self::ARTICLE_DELETE => Grant::ALLOW_OWNER],
        ));

        $this->addToAssertionCount(1);
    }

    public function testEditorRaisesGrantUpToOwnLevel(): void
    {
        $guard = $this->createGuard(
            editorRoles: [AnzuUser::ROLE_ADMIN],
            editorGrants: [self::USER_UPDATE => Grant::ALLOW, self::ARTICLE_READ => Grant::ALLOW_OWNER],
        );
        $user = $this->createUser([AnzuUser::ROLE_ADMIN]);

        $guard->assertUserWrite($user, $this->createDto(grants: [self::ARTICLE_READ => Grant::ALLOW_OWNER]));
        $this->addToAssertionCount(1);

        $this->expectException(AccessDeniedException::class);
        $guard->assertUserWrite($user, $this->createDto(grants: [self::ARTICLE_READ => Grant::ALLOW]));
    }

    public function testEditorCannotAddGroupWithGrantNotHeld(): void
    {
        $guard = $this->createGuard(editorRoles: [AnzuUser::ROLE_ADMIN], editorGrants: [self::USER_UPDATE => Grant::ALLOW]);
        $dto = $this->createDto();
        $dto->setPermissionGroups(new ArrayCollection([$this->createGroup([self::ARTICLE_DELETE => Grant::ALLOW])]));

        $this->expectException(AccessDeniedException::class);
        $guard->assertUserWrite($this->createUser([AnzuUser::ROLE_ADMIN]), $dto);
    }

    public function testEditorKeepsGroupWithGrantNotHeld(): void
    {
        $guard = $this->createGuard(editorRoles: [AnzuUser::ROLE_ADMIN], editorGrants: [self::USER_UPDATE => Grant::ALLOW]);
        $group = $this->createGroup([self::ARTICLE_DELETE => Grant::ALLOW]);
        $user = $this->createUser([AnzuUser::ROLE_ADMIN]);
        $user->setPermissionGroups(new ArrayCollection([$group]));
        $dto = $this->createDto();
        $dto->setPermissionGroups(new ArrayCollection([$group]));

        $guard->assertUserWrite($user, $dto);

        $this->addToAssertionCount(1);
    }

    public function testEditorCannotRaiseGroupGrantNotHeld(): void
    {
        $guard = $this->createGuard(editorRoles: [AnzuUser::ROLE_ADMIN], editorGrants: [self::USER_UPDATE => Grant::ALLOW]);
        $group = $this->createGroup([self::ARTICLE_DELETE => Grant::DENY]);

        $guard->assertPermissionGroupWrite($group, $this->createGroup([self::ARTICLE_DELETE => Grant::DENY, self::USER_UPDATE => Grant::ALLOW]));
        $this->addToAssertionCount(1);

        $this->expectException(AccessDeniedException::class);
        $guard->assertPermissionGroupWrite($group, $this->createGroup([self::ARTICLE_DELETE => Grant::ALLOW]));
    }

    /**
     * @param list<string> $editorRoles
     * @param array<string, int> $editorGrants
     */
    private function createGuard(array $editorRoles, array $editorGrants): EditorGrantGuard
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')
            ->willReturnCallback(static fn (string $role): bool => in_array(needle: $role, haystack: $editorRoles, strict: true));
        $currentUserProvider = $this->createStub(CurrentAnzuUserProvider::class);
        $currentUserProvider->method('getCurrentUser')
            ->willReturn($this->createUser($editorRoles, $editorGrants));

        return new EditorGrantGuard($security, $currentUserProvider);
    }

    /**
     * @param list<string> $roles
     * @param array<string, int> $grants
     */
    private function createUser(array $roles, array $grants = []): User
    {
        $user = new User();
        $user->setRoles($roles);
        $user->setPermissions($grants);

        return $user;
    }

    private function createSuperAdmin(): User
    {
        return $this->createUser([AnzuUser::ROLE_SUPER_ADMIN]);
    }

    /**
     * @param list<string> $roles
     * @param array<string, int> $grants
     */
    private function createDto(array $roles = [AnzuUser::ROLE_ADMIN], array $grants = []): UserDto
    {
        return new UserDto()
            ->setRoles($roles)
            ->setPermissions($grants)
            ->setPermissionGroups(new ArrayCollection());
    }

    /**
     * @param array<string, int> $grants
     */
    private function createGroup(array $grants): PermissionGroup
    {
        $group = new PermissionGroup();
        $group->setPermissions($grants);

        return $group;
    }
}
