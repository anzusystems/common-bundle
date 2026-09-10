<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\DependencyInjection;

use AnzuSystems\CommonBundle\DependencyInjection\AnzuSystemsCommonExtension;
use AnzuSystems\CommonBundle\Mcp\McpRateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore;

final class AnzuSystemsCommonExtensionTest extends TestCase
{
    private const string EXTENSION_ALIAS = 'anzu_systems_common';
    private const string APP_REDIS_ID = 'app.redis';
    private const string LOCK_STORE_ID = 'anzu_systems_common.mcp.rate_limiter_lock_store';
    private const string LOCK_FACTORY_ID = 'anzu_systems_common.mcp.rate_limiter_lock_factory';
    private const string HOST_LOCK_FACTORY_ID = 'app.mcp.lock_factory';
    private const string LOCK_FACTORY_ARGUMENT = '$lockFactory';

    public function testRateLimiterLocksTheSlidingWindowWithARedisLockFactory(): void
    {
        $container = $this->buildContainer();

        $lockFactory = $container->getDefinition(McpRateLimiter::class)
            ->getArgument(self::LOCK_FACTORY_ARGUMENT);
        self::assertInstanceOf(Reference::class, $lockFactory);
        self::assertSame(self::LOCK_FACTORY_ID, (string) $lockFactory);

        $lockFactoryDefinition = $container->getDefinition(self::LOCK_FACTORY_ID);
        self::assertSame(LockFactory::class, $lockFactoryDefinition->getClass());
        self::assertSame(self::LOCK_STORE_ID, (string) $lockFactoryDefinition->getArgument('$store'));

        $lockStoreDefinition = $container->getDefinition(self::LOCK_STORE_ID);
        self::assertSame(RedisStore::class, $lockStoreDefinition->getClass());
        self::assertSame(self::APP_REDIS_ID, (string) $lockStoreDefinition->getArgument('$redis'));
    }

    public function testConfiguredLockFactoryReplacesTheRedisOne(): void
    {
        $container = $this->buildContainer(['lock_factory' => self::HOST_LOCK_FACTORY_ID]);

        $lockFactory = $container->getDefinition(McpRateLimiter::class)
            ->getArgument(self::LOCK_FACTORY_ARGUMENT);
        self::assertSame(self::HOST_LOCK_FACTORY_ID, (string) $lockFactory);
        self::assertFalse($container->hasDefinition(self::LOCK_FACTORY_ID));
        self::assertFalse($container->hasDefinition(self::LOCK_STORE_ID));
    }

    private function buildContainer(array $rateLimiter = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->registerExtension($this->createMcpExtension());
        $container->prependExtensionConfig(self::EXTENSION_ALIAS, $this->createConfig($rateLimiter));

        $extension = new AnzuSystemsCommonExtension();
        $extension->prepend($container);
        $extension->load([], $container);

        return $container;
    }

    private function createConfig(array $rateLimiter): array
    {
        $mongo = [
            'uri' => 'mongodb://localhost:27017',
            'username' => 'anzu',
            'password' => 'anzu',
            'database' => 'logs',
        ];

        return [
            'settings' => [
                'app_redis' => self::APP_REDIS_ID,
            ],
            'logs' => [
                'messenger_transport' => [
                    'name' => 'log',
                    'dsn' => 'in-memory://',
                ],
                'journal' => [
                    'mongo' => $mongo,
                ],
                'audit' => [
                    'mongo' => $mongo,
                ],
            ],
            'mcp' => [
                'enabled' => true,
                'server_name' => 'core_cms',
                'allowed_hosts' => ['mcp.localhost'],
                'tool_permissions' => [
                    'search_app_logs' => 'mcp_tool_searchAppLogs',
                ],
                'rate_limiter' => $rateLimiter,
            ],
        ];
    }

    private function createMcpExtension(): Extension
    {
        return new class() extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'mcp';
            }
        };
    }
}
