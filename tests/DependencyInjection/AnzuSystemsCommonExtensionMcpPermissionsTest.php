<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\DependencyInjection;

use AnzuSystems\CommonBundle\DependencyInjection\AnzuSystemsCommonExtension;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolPermission;
use AnzuSystems\CommonBundle\Mcp\Tool\GetLogsByContextTool;
use AnzuSystems\CommonBundle\Mcp\Tool\SearchAppLogsTool;
use AnzuSystems\CommonBundle\Mcp\Tool\SearchAuditLogsTool;
use AnzuSystems\CommonBundle\Security\PermissionConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

final class AnzuSystemsCommonExtensionMcpPermissionsTest extends TestCase
{
    private const string EXTENSION_ALIAS = 'anzu_systems_common';
    private const string PROJECT_ACTION = 'listSites';
    private const array MONGO = [
        'uri' => 'mongodb://localhost',
        'username' => 'user',
        'password' => 'password',
        'database' => 'logs',
    ];

    public function testBundleToolPermissionsAreMergedWithProjectPermissions(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->registerExtension($this->createMcpExtensionStub());
        $extension = new AnzuSystemsCommonExtension();
        $container->registerExtension($extension);
        $container->loadFromExtension(self::EXTENSION_ALIAS, $this->createConfig());

        $extension->prepend($container);
        $extension->load($container->getExtensionConfig(self::EXTENSION_ALIAS), $container);

        $permissions = $container->getDefinition(PermissionConfig::class)
            ->getArgument('$config');
        self::assertIsArray($permissions);
        $actions = array_keys($permissions[PermissionConfig::PRM_CONFIG][McpToolPermission::SUBJECT]);
        self::assertContains(McpToolPermission::toAction(SearchAppLogsTool::NAME), $actions);
        self::assertContains(McpToolPermission::toAction(SearchAuditLogsTool::NAME), $actions);
        self::assertContains(McpToolPermission::toAction(GetLogsByContextTool::NAME), $actions);
        self::assertContains(self::PROJECT_ACTION, $actions);
        self::assertArrayHasKey(
            McpToolPermission::SUBJECT,
            $permissions[PermissionConfig::PRM_TRANSLATION]['subjects'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function createConfig(): array
    {
        return [
            'settings' => [
                'app_redis' => 'redis://localhost',
            ],
            'logs' => [
                'messenger_transport' => [
                    'name' => 'logs',
                    'dsn' => 'in-memory://',
                ],
                'journal' => [
                    'mongo' => self::MONGO,
                ],
                'audit' => [
                    'mongo' => self::MONGO,
                ],
            ],
            'mcp' => [
                'enabled' => true,
                'allowed_hosts' => ['localhost'],
            ],
            'permissions' => [
                PermissionConfig::PRM_CONFIG => [
                    McpToolPermission::SUBJECT => [
                        self::PROJECT_ACTION => [],
                    ],
                ],
            ],
        ];
    }

    private function createMcpExtensionStub(): Extension
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
