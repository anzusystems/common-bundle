<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Helper;

use AnzuSystems\Contracts\Entity\Interfaces\IdentifiableInterface;

final class UuidHelper
{
    /**
     * Encodes identifiable entity into uuid like string.
     * Examples:
     * UuidHelper::anzuId(article, core, 1) = article0-core-0000-0000-000000000001
     * UuidHelper::anzuId(image, dam, 2345, 2) = image000-dam0-0000-0002-000000002345
     * UuidHelper::anzuId(topic, forum, 67) = topic000-foru-0000-0000-000000000067.
     */
    public static function getAnzuId(string $resourceName, string $system, int $id, int $groupId = 0): string
    {
        $resourcePart = substr(str_pad($resourceName, 8, '0', STR_PAD_RIGHT), 0, 8);
        $systemPart = substr(str_pad($system, 4, '0', STR_PAD_RIGHT), 0, 4);
        $groupPart = substr(str_pad((string) $groupId, 4, '0', STR_PAD_LEFT), 0, 4);
        $idPart = str_pad((string) $id, 12, '0', STR_PAD_LEFT);

        return sprintf('%s-%s-0000-%s-%s', $resourcePart, $systemPart, $groupPart, $idPart);
    }

    public static function getAnzuIdByIdentifiable(IdentifiableInterface $identifiable): string
    {
        return self::getAnzuId(
            $identifiable::getResourceName(),
            $identifiable::getSystem(),
            (int) $identifiable->getId(),
        );
    }
}
