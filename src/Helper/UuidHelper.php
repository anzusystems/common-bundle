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
        $resourcePart = substr(string: str_pad(string: $resourceName, length: 8, pad_string: '0', pad_type: STR_PAD_RIGHT), offset: 0, length: 8);
        $systemPart = substr(string: str_pad(string: $system, length: 4, pad_string: '0', pad_type: STR_PAD_RIGHT), offset: 0, length: 4);
        $groupPart = substr(string: str_pad(string: (string) $groupId, length: 4, pad_string: '0', pad_type: STR_PAD_LEFT), offset: 0, length: 4);
        $idPart = str_pad(string: (string) $id, length: 12, pad_string: '0', pad_type: STR_PAD_LEFT);

        return sprintf('%s-%s-0000-%s-%s', $resourcePart, $systemPart, $groupPart, $idPart);
    }

    public static function getAnzuIdByIdentifiable(IdentifiableInterface $identifiable): string
    {
        return self::getAnzuId(
            resourceName: $identifiable::getResourceName(),
            system: $identifiable::getSystem(),
            id: (int) $identifiable->getId(),
        );
    }
}
