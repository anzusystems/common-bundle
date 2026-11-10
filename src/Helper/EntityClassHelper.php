<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Helper;

use Doctrine\Persistence\Proxy;

final class EntityClassHelper
{
    /**
     * Without native lazy objects, Doctrine gives a reference as a generated proxy class that extends the entity class.
     *
     * @return class-string
     */
    public static function getRealClass(object $entity): string
    {
        if ($entity instanceof Proxy) {
            return (string) get_parent_class($entity);
        }

        return $entity::class;
    }
}
