<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Repository;

use AnzuSystems\CommonBundle\Repository\AbstractAnzuRepository;
use AnzuSystems\CommonBundle\Tests\Data\Entity\User;

/**
 * @extends AbstractAnzuRepository<User>
 */
final class UserRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return User::class;
    }
}
