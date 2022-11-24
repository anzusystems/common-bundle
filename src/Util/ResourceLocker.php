<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Util;

use AnzuSystems\Contracts\Entity\Interfaces\BaseIdentifiableInterface;
use Doctrine\ORM\EntityManagerInterface;
use Redis;
use RuntimeException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\Store\RedisStore;

final class ResourceLocker
{
    public const REDIS_LOCK_PREFIX = 'res_lock_';

    /**
     * @var array<string, LockInterface>
     */
    private array $locks = [];
    private ?RedisStore $redisStore = null;
    private ?LockFactory $lockFactory = null;

    public function __construct(
        private readonly Redis $appRedis,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function lock(BaseIdentifiableInterface | string $resource, bool $blocking = true): bool
    {
        $lock = $this->createLock($resource);
        $acquired = $lock->acquire();
        $resourceDirty = false;
        if (false === $acquired) {
            if (false === $blocking) {
                throw new RuntimeException('locked_by_another_process');
            }
            $acquired = $lock->acquire(true);
            $resourceDirty = true;
        }
        if ($resourceDirty && $resource instanceof BaseIdentifiableInterface) {
            $this->entityManager->refresh($resource);
        }
        $this->locks[$this->getLockName($resource)] = $lock;

        return $acquired;
    }

    public function unLock(BaseIdentifiableInterface | string $resource): void
    {
        $lockName = $this->getLockName($resource);
        if (array_key_exists($lockName, $this->locks)) {
            $this->locks[$lockName]->release();
            unset($this->locks[$lockName]);
        }
    }

    public function unlockAll(): void
    {
        foreach ($this->locks as $lockName => $lock) {
            $lock->release();
            unset($this->locks[$lockName]);
        }
    }

    private function getLockName(BaseIdentifiableInterface | string $resource): string
    {
        if ($resource instanceof BaseIdentifiableInterface) {
            return self::REDIS_LOCK_PREFIX . $resource::getResourceName() . '_' . ((string) $resource->getId());
        }

        return self::REDIS_LOCK_PREFIX . $resource;
    }

    private function createLock(BaseIdentifiableInterface | string $resource): LockInterface
    {
        return $this->getLockFactory()->createLock($this->getLockName($resource), 60);
    }

    private function getRedisStore(): RedisStore
    {
        if (null === $this->redisStore) {
            $this->redisStore = new RedisStore($this->appRedis);
        }

        return $this->redisStore;
    }

    private function getLockFactory(): LockFactory
    {
        if (null === $this->lockFactory) {
            $this->lockFactory = new LockFactory($this->getRedisStore());
        }

        return $this->lockFactory;
    }
}
