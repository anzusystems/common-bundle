<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Entity;

use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\Contracts\Entity\Interfaces\CopyableInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class User extends AnzuUser implements CopyableInterface
{
    #[ORM\Column]
    private string $name;

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

    public function __construct()
    {
        $this->setId(null);
        $this->setRoles([self::ROLE_USER]);
        $this->setName('');
        $this->setEnabled(true);
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
