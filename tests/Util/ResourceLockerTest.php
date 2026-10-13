<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Util;

use AnzuSystems\CommonBundle\Tests\AnzuKernelTestCase;
use AnzuSystems\CommonBundle\Util\ResourceLocker;
use Redis;

final class ResourceLockerTest extends AnzuKernelTestCase
{
    private const string RESOURCE = 'resource_locker_test';
    private const float GIVEN_TTL = 1_800.0;
    private const int MILLISECONDS_IN_SECOND = 1_000;
    private const int TOLERANCE_MILLISECONDS = 5_000;

    private ResourceLocker $resourceLocker;
    private Redis $redis;

    protected function setUp(): void
    {
        $this->resourceLocker = static::getContainer()->get(ResourceLocker::class . '.test');
        $this->redis = static::getContainer()->get('TestRedis');
    }

    protected function tearDown(): void
    {
        $this->resourceLocker->unLock(self::RESOURCE);
    }

    public function testLockWithoutTtlExpiresAfterDefaultTtl(): void
    {
        $this->resourceLocker->lock(self::RESOURCE, blocking: false);

        $this->assertRemainingTtl(ResourceLocker::DEFAULT_TTL);
    }

    public function testLockExpiresAfterGivenTtl(): void
    {
        $this->resourceLocker->lock(self::RESOURCE, blocking: false, ttl: self::GIVEN_TTL);

        $this->assertRemainingTtl(self::GIVEN_TTL);
    }

    private function assertRemainingTtl(float $ttl): void
    {
        $remaining = $this->redis->pttl(ResourceLocker::REDIS_LOCK_PREFIX . self::RESOURCE);
        $expected = (int) ($ttl * self::MILLISECONDS_IN_SECOND);

        self::assertLessThanOrEqual($expected, $remaining);
        self::assertGreaterThan($expected - self::TOLERANCE_MILLISECONDS, $remaining);
    }
}
