<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\DependencyInjection;

use AnzuSystems\CommonBundle\AnzuSystemsCommonBundle;
use AnzuSystems\CommonBundle\DependencyInjection\AnzuSystemsCommonExtension;
use AnzuSystems\CommonBundle\Mcp\Handler\McpToolReferenceHandler;
use AnzuSystems\CommonBundle\Mcp\McpRateLimiter;
use AnzuSystems\CommonBundle\Mcp\Security\McpDeleteDependencyProviderInterface;
use AnzuSystems\CommonBundle\Mcp\Security\McpDeleteGuard;
use AnzuSystems\CommonBundle\Mcp\Security\McpPreviewHashReservation;
use AnzuSystems\CommonBundle\Mcp\Security\McpWriteConfirmation;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
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
    private const string FALLBACK_LOCALE = 'sk';
    private const string CACHE_POOL_ID = 'example.cache';

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

    public function testDeleteDependencyProvidersReachTheDeleteGuardByEntityClass(): void
    {
        $container = $this->buildContainer();

        $autoconfigured = $container->getAutoconfiguredInstanceof()[McpDeleteDependencyProviderInterface::class];
        self::assertTrue($autoconfigured->hasTag(AnzuSystemsCommonBundle::TAG_MCP_DELETE_DEPENDENCY_PROVIDER));

        $dependencyProviders = $container->getDefinition(McpDeleteGuard::class)
            ->getArgument('$dependencyProviders');
        self::assertInstanceOf(ServiceLocatorArgument::class, $dependencyProviders);
        $taggedIterator = $dependencyProviders->getTaggedIteratorArgument();
        self::assertSame(AnzuSystemsCommonBundle::TAG_MCP_DELETE_DEPENDENCY_PROVIDER, $taggedIterator->getTag());
        self::assertSame('getSupportedEntity', $taggedIterator->getDefaultIndexMethod(false));
    }

    /**
     * @dataProvider confirmationFallbackLocaleProvider
     *
     * @param array<string, string> $mcp
     */
    public function testConfirmationFallbackLocaleReachesTheWriteConfirmation(array $mcp, ?string $expectedLocale): void
    {
        $container = $this->buildContainer(mcp: $mcp);

        self::assertSame(
            $expectedLocale,
            $container->getDefinition(McpWriteConfirmation::class)->getArgument('$fallbackLocale'),
        );
    }

    /**
     * @return array<string, array{array<string, string>, string|null}>
     */
    public static function confirmationFallbackLocaleProvider(): array
    {
        return [
            'default is the locale of the translator' => [[], null],
            'configured locale' => [['confirmation_fallback_locale' => self::FALLBACK_LOCALE], self::FALLBACK_LOCALE],
        ];
    }

    public function testPreviewHashReservationUsesTheCachePoolOfTheRateLimiter(): void
    {
        $container = $this->buildContainer(rateLimiter: ['cache_pool' => self::CACHE_POOL_ID]);

        self::assertEquals(
            new Reference(self::CACHE_POOL_ID),
            $container->getDefinition(McpPreviewHashReservation::class)->getArgument('$cachePool'),
        );
    }

    public function testMcpWithoutTheLogToolsPermissionFailsAtCompileTime(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('log_tools_permission');

        $this->buildContainer(mcp: ['log_tools_permission' => null]);
    }

    public function testToolReferenceHandlerGetsTheRegistryOfTheServer(): void
    {
        $container = $this->buildContainer();

        $registry = $container->getDefinition(McpToolReferenceHandler::class)
            ->getArgument('$registry');
        self::assertInstanceOf(Reference::class, $registry);
        self::assertSame('mcp.server.example.registry', (string) $registry);
    }

    private function buildContainer(array $rateLimiter = [], array $mcp = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->registerExtension($this->createMcpExtension());
        $container->prependExtensionConfig(self::EXTENSION_ALIAS, $this->createConfig($rateLimiter, $mcp));

        $extension = new AnzuSystemsCommonExtension();
        $extension->prepend($container);
        $extension->load([], $container);

        return $container;
    }

    private function createConfig(array $rateLimiter, array $mcp): array
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
            'mcp' => $mcp + [
                'enabled' => true,
                'server_name' => 'example',
                'allowed_hosts' => ['mcp.localhost'],
                'log_tools_permission' => 'mcp_tool_searchAppLogs',
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
