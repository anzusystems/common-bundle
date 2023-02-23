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
final class UserDto
{
    #[Serialize]
    private ?int $id = null;

    #[Assert\Email(message: ValidationException::ERROR_FIELD_INVALID)]
    #[Assert\Length(max: 256, maxMessage: ValidationException::ERROR_FIELD_LENGTH_MAX)]
    #[Assert\NotBlank(message: ValidationException::ERROR_FIELD_EMPTY)]
    #[Serialize]
    private string $email = '';

    #[Assert\Valid]
    #[Serialize]
    private Person $person;

    #[Assert\Valid]
    #[Serialize]
    private Avatar $avatar;

    #[Serialize]
    private bool $enabled = true;

    #[Serialize]
    private array $roles = [AnzuUser::ROLE_USER];

    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    private array $permissions = [];

    #[Serialize(handler: EntityIdHandler::class, type: new ContainerParam(AnzuPermissionGroup::class))]
    private Collection $permissionGroups;

    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    private array $data = [];

    public function __construct()
    {
        $this->setPermissionGroups(new ArrayCollection());
        $this->setPerson(new Person());
        $this->setAvatar(new Avatar());
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPerson(): Person
    {
        return $this->person;
    }

    public function setPerson(Person $person): self
    {
        $this->person = $person;

        return $this;
    }

    public function getAvatar(): Avatar
    {
        return $this->avatar;
    }

    public function setAvatar(Avatar $avatar): self
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
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

    /**
     * @return Collection<int, AnzuPermissionGroup>
     */
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
