<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\Permission;

use AnzuSystems\Contracts\Entity\AnzuPermissionGroup;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use AnzuSystems\SerializerBundle\Metadata\ContainerParam;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final class PermissionUserUpdateDto
{
    #[Serialize]
    private bool $enabled = false;

    #[Serialize]
    private array $roles = [];

    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    private array $permissions = [];

    #[Serialize(handler: EntityIdHandler::class, type: new ContainerParam(AnzuPermissionGroup::class))]
    private Collection $permissionGroups;

    public function __construct()
    {
        $this->setPermissionGroups(new ArrayCollection());
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function setPermissions(array $permissions): self
    {
        $this->permissions = $permissions;

        return $this;
    }

    public function getPermissionGroups(): Collection
    {
        return $this->permissionGroups;
    }

    public function setPermissionGroups(Collection $permissionGroups): self
    {
        $this->permissionGroups = $permissionGroups;

        return $this;
    }
}
