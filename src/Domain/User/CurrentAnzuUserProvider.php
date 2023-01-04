<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Domain\User;

use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Entity\AnzuUser;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\SecurityBundle\Security;

class CurrentAnzuUserProvider
{
    private ?AnzuUser $currentUser = null;

    /**
     * @param class-string<AnzuUser> $userEntityClass
     */
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $userEntityClass,
    ) {
    }

    public function getCurrentUser(): AnzuUser
    {
        if (null === $this->currentUser || 'test' === AnzuApp::getAppEnv()) {
            $this->currentUser = $this->security->getUser() instanceof AnzuUser
                ? $this->security->getUser()
                : $this->entityManager->find($this->userEntityClass, AnzuApp::getUserIdAnonymous());
        }
        if (false === ($this->currentUser instanceof AnzuUser)) {
            throw new LogicException('Anonymous user should be set at this step');
        }
        if (false === $this->entityManager->contains($this->currentUser)) {
            $this->currentUser = $this->entityManager->find($this->userEntityClass, $this->currentUser->getId());
        }
        if (false === ($this->currentUser instanceof AnzuUser)) {
            throw new LogicException('Current user should be set at this step');
        }

        return $this->currentUser;
    }

    /**
     * Override current user.
     */
    public function setCurrentUser(AnzuUser $user): AnzuUser
    {
        $this->currentUser = $user;

        return $user;
    }

    public function setAdminCurrentUser(): AnzuUser
    {
        $adminUser = $this->entityManager->find($this->userEntityClass, AnzuApp::getUserIdAdmin());
        if (false === ($adminUser instanceof AnzuUser)) {
            throw new LogicException('Admin user not found');
        }

        return $this->setCurrentUser($adminUser);
    }

    public function isCurrentUser(AnzuUser $user): bool
    {
        return $user->getId() === $this->getCurrentUser()->getId();
    }
}
