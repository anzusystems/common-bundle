<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Security\Voter;

use AnzuSystems\CommonBundle\Traits\SecurityAwareTrait;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\Contracts\Entity\Interfaces\OwnersAwareInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Security\Grant;
use RuntimeException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

abstract class AbstractVoter extends Voter
{
    use SecurityAwareTrait;

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, $this->getSupportedPermissions(), true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var AnzuUser $user */
        $user = $token->getUser();

        // If role admin, grant access
        if ($this->security->isGranted(AnzuUser::ROLE_ADMIN)) {
            return true;
        }

        // If specific business logic allows, grant access
        if ($this->businessLogicVote($subject, $user)) {
            return true;
        }

        return $this->permissionVote($attribute, $subject, $user);
    }

    protected function businessLogicVote(mixed $subject, AnzuUser $user): bool
    {
        return false;
    }

    protected function permissionVote(string $attribute, mixed $subject, AnzuUser $user): bool
    {
        $userPermissions = $user->getPermissions();
        if (false === array_key_exists($attribute, $userPermissions)) {
            return false;
        }
        $userPermissionGrant = $userPermissions[$attribute];

        return match ($userPermissionGrant) {
            Grant::DENY => false,
            Grant::ALLOW => true,
            Grant::ALLOW_OWNER => $this->resolveAllowOwner($subject, $user),
            default => throw new RuntimeException('User permission could not be resolved!'),
        };
    }

    protected function resolveAllowOwner(mixed $subject, AnzuUser $user): bool
    {
        if ($subject instanceof OwnersAwareInterface) {
            return $subject->getOwners()->exists(
                fn (int $key, AnzuUser $owner): bool => $owner->is($user)
            );
        }
        if ($subject instanceof UserTrackingInterface) {
            return $subject->getCreatedBy()->is($user);
        }

        return false;
    }

    abstract protected function getSupportedPermissions(): array;
}
