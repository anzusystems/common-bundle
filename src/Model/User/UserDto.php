<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\User;

use AnzuSystems\CommonBundle\Validator\Constraints\UniqueEntityDto;
use AnzuSystems\Contracts\Entity\AnzuPermissionGroup;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use AnzuSystems\SerializerBundle\Metadata\ContainerParam;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[UniqueEntityDto(entity: AnzuUser::class, fields: ['id'])]
#[UniqueEntityDto(entity: AnzuUser::class, fields: ['email'])]
class UserDto extends BaseUserDto
{
    #[Serialize]
    protected array $roles = [AnzuUser::ROLE_USER];

    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    protected array $permissions = [];

    #[Serialize(handler: EntityIdHandler::class, type: new ContainerParam(AnzuPermissionGroup::class))]
    protected Collection $permissionGroups;

    #[Serialize]
    protected bool $enabled = true;
    protected array $resolvedPermissions = [];
    protected DateTimeImmutable $createdAt;
    protected DateTimeImmutable $modifiedAt;
    protected AnzuUser $createdBy;
    protected AnzuUser $modifiedBy;

    public function __construct()
    {
        parent::__construct();
        $this->setPermissionGroups(new ArrayCollection());
    }

    public static function createFromUser(AnzuUser $user): static
    {
        /** @psalm-suppress UndefinedMethod */
        return parent::createFromUser($user)
            ->setRoles($user->getRoles())
            ->setPermissions($user->getPermissions())
            ->setResolvedPermissions($user->getResolvedPermissions())
            ->setPermissionGroups($user->getPermissionGroups())
            ->setEnabled($user->isEnabled())
            ->setCreatedAt($user->getCreatedAt())
            ->setModifiedAt($user->getModifiedAt())
            ->setCreatedBy($user->getCreatedBy())
            ->setModifiedBy($user->getModifiedBy())
        ;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function setPermissions(array $permissions): static
    {
        $this->permissions = $permissions;

        return $this;
    }

    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    public function getResolvedPermissions(): array
    {
        return $this->resolvedPermissions;
    }

    public function setResolvedPermissions(array $resolvedPermissions): static
    {
        $this->resolvedPermissions = $resolvedPermissions;

        return $this;
    }

    /**
     * @return Collection<int, AnzuPermissionGroup>
     */
    public function getPermissionGroups(): Collection
    {
        return $this->permissionGroups;
    }

    public function setPermissionGroups(Collection $permissionGroups): static
    {
        $this->permissionGroups = $permissionGroups;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    #[Serialize]
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    #[Serialize]
    public function getModifiedAt(): DateTimeImmutable
    {
        return $this->modifiedAt;
    }

    public function setModifiedAt(DateTimeImmutable $modifiedAt): static
    {
        $this->modifiedAt = $modifiedAt;

        return $this;
    }

    #[Serialize(handler: EntityIdHandler::class, type: new ContainerParam(AnzuUser::class))]
    public function getCreatedBy(): AnzuUser
    {
        return $this->createdBy;
    }

    public function setCreatedBy(AnzuUser $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    #[Serialize(handler: EntityIdHandler::class, type: new ContainerParam(AnzuUser::class))]
    public function getModifiedBy(): AnzuUser
    {
        return $this->modifiedBy;
    }

    public function setModifiedBy(AnzuUser $modifiedBy): self
    {
        $this->modifiedBy = $modifiedBy;

        return $this;
    }
}
