<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Entity;

use AnzuSystems\CommonBundle\Tests\Data\Repository\UserRepository;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\Contracts\Entity\Interfaces\CopyableInterface;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User extends AnzuUser implements CopyableInterface
{
    #[ORM\Column(type: Types::STRING)]
    private string $name;

    /**
     * Assigned permission groups.
     *
     * Override in your project to get relations:
     */
     #[ORM\ManyToMany(targetEntity: PermissionGroup::class, inversedBy: 'users', fetch: 'EXTRA_LAZY', indexBy: 'id')]
     #[ORM\JoinTable]
     #[Serialize(handler: EntityIdHandler::class, type: PermissionGroup::class)]
    protected Collection $permissionGroups;

    public function __construct()
    {
        parent::__construct();
        $this->setId(null);
        $this->setRoles([self::ROLE_USER]);
        $this->setName('');
        $this->setEnabled(true);
    }

    public function getUsername(): string
    {
        return $this->getUserIdentifier();
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->getId();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): User
    {
        $this->name = $name;

        return $this;
    }

    public function __copy(): self
    {
        return (new self())
            ->setName('Tester')
            ->setRoles([])
            ->setEnabled(false)
        ;
    }
}
