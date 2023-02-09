<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Entity;

use AnzuSystems\Contracts\Entity\AnzuPermissionGroup;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use AnzuSystems\SerializerBundle\Handler\Handlers\EntityIdHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class PermissionGroup extends AnzuPermissionGroup
{
    use UserTrackingTrait;

    /**
     * List of users who belongs to permission group.
     *
     * Override in your project to get relations:
     */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'permissionGroups', indexBy: 'id')]
    #[Serialize(handler: EntityIdHandler::class, type: User::class)]
    protected Collection $users;

    public function __construct()
    {
        parent::__construct();
        $this->setUsers(new ArrayCollection());
    }

    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function setUsers(Collection $users): self
    {
        $this->users = $users;

        return $this;
    }
}
