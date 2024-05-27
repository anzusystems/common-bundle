<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\User;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Validator\Constraints\UniqueEntityDto;
use AnzuSystems\Contracts\Entity\AnzuPermissionGroup;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\Contracts\Entity\Embeds\Avatar;
use AnzuSystems\Contracts\Entity\Embeds\Person;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use AnzuSystems\SerializerBundle\Metadata\ContainerParam;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

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

    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    protected array $data = [];

    #[Serialize]
    protected bool $enabled = true;

    protected array $resolvedPermissions = [];

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
        ;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
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
}
