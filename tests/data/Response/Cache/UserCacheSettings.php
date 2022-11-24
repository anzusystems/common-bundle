<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Response\Cache;

use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\Contracts\Response\Cache\AbstractCacheSettings;

final class UserCacheSettings extends AbstractCacheSettings
{
    public const XKEY_PREFIX = 'user';

    public function __construct(
        private readonly AnzuUser $user,
    ) {
        parent::__construct(60);
    }
    public static function buildXKeyFromObject(object $data): string
    {
        if ($data instanceof AnzuUser) {
            return self::XKEY_PREFIX . '-' . ((string) $data->getId());
        }
        return self::XKEY_PREFIX;
    }
    protected function getXKeys(): array
    {
        return [
            self::buildXKeyFromObject($this->user),
        ];
    }
}
