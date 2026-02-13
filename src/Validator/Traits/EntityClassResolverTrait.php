<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Validator\Traits;

use AnzuSystems\Contracts\Entity\AnzuUser;

trait EntityClassResolverTrait
{
    /**
     * @var class-string
     */
    private readonly string $userEntityClass;

    /**
     * @param class-string $entityClass
     *
     * @return class-string
     */
    private function resolveEntityClass(string $entityClass): string
    {
        if (AnzuUser::class === $entityClass) {
            return $this->userEntityClass;
        }

        return $entityClass;
    }
}
