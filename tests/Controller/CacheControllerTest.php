<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Controller;

use AnzuSystems\CommonBundle\Tests\Data\Response\Cache\UserCacheSettings;

final class CacheControllerTest extends AbstractControllerTest
{
    public function testCache(): void
    {
        $this->get(uri: '/dummy/cache-test');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            UserCacheSettings::buildXKeyFromObject($this->user),
            self::$client->getResponse()->headers->get('xkey')
        );
        self::assertStringContainsString(
            UserCacheSettings::getProjectXkey(),
            self::$client->getResponse()->headers->get('xkey')
        );
        self::assertSame(
            '60',
            self::$client->getResponse()->headers->get('X-Cache-Control-TTL')
        );
        self::assertSame(
            '1',
            self::$client->getResponse()->headers->get('X-Remove-Cookie')
        );
    }
}
