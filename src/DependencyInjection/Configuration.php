<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DependencyInjection;

use AnzuSystems\CommonBundle\Exception\Handler\AccessDeniedExceptionHandler;
use AnzuSystems\CommonBundle\Exception\Handler\AppReadOnlyModeExceptionHandler;
use AnzuSystems\CommonBundle\Exception\Handler\DefaultExceptionHandler;
use AnzuSystems\CommonBundle\Exception\Handler\ExceptionHandlerInterface;
use AnzuSystems\CommonBundle\Exception\Handler\NotFoundExceptionHandler;
use AnzuSystems\CommonBundle\Exception\Handler\ValidationExceptionHandler;
use AnzuSystems\CommonBundle\HealthCheck\Module\DataMountModule;
use AnzuSystems\CommonBundle\HealthCheck\Module\ForwardIpModule;
use AnzuSystems\CommonBundle\HealthCheck\Module\MongoModule;
use AnzuSystems\CommonBundle\HealthCheck\Module\MysqlModule;
use AnzuSystems\CommonBundle\HealthCheck\Module\OpCacheModule;
use AnzuSystems\CommonBundle\HealthCheck\Module\RedisModule;
use AnzuSystems\CommonBundle\Security\PermissionConfig;
use AnzuSystems\CommonBundle\Serializer\Exception\SerializerExceptionHandler;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\Contracts\Security\Grant;
use Symfony\Bundle\FrameworkBundle\Command\AssetsInstallCommand;
use Symfony\Bundle\FrameworkBundle\Command\CacheWarmupCommand;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;

final class Configuration implements ConfigurationInterface
{
    private const EXCEPTION_HANDLERS = [
        NotFoundExceptionHandler::class,
        ValidationExceptionHandler::class,
        AppReadOnlyModeExceptionHandler::class,
        AccessDeniedExceptionHandler::class,
        SerializerExceptionHandler::class,
    ];
    private const HEALTH_CHECK_MODULES = [
        MysqlModule::class,
        MongoModule::class,
        RedisModule::class,
        OpCacheModule::class,
        ForwardIpModule::class,
        DataMountModule::class,
    ];
    private const DEFAULT_UNLOCKED_COMMANDS = [
        ConsumeMessagesCommand::class,
        CacheWarmupCommand::class,
        AssetsInstallCommand::class,
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('anzu_systems_common');

        $treeBuilder->getRootNode()
            ->children()
                ->append($this->addSettingsSection())
                ->append($this->addErrorsSection())
                ->append($this->addLogSection())
                ->append($this->addHealthCheckSection())
                ->append($this->addPermissionsSection())
                ->append($this->addJobsSection())
            ->end()
        ;

        return $treeBuilder;
    }

    private function addPermissionsSection(): NodeDefinition
    {
        return (new TreeBuilder('permissions'))->getRootNode()
            ->addDefaultsIfNotSet()
            ->canBeDisabled()
            ->children()
                ->arrayNode(PermissionConfig::PRM_DEFAULT_GRANTS)
                    ->defaultValue([Grant::ALLOW, Grant::DENY])
                    ->integerPrototype()->end()
                ->end()
                ->arrayNode(PermissionConfig::PRM_ROLES)
                    ->defaultValue([AnzuUser::ROLE_USER, AnzuUser::ROLE_ADMIN])
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode(PermissionConfig::PRM_CONFIG)
                    ->arrayPrototype()
                        ->useAttributeAsKey('name')
                        ->arrayPrototype()
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->useAttributeAsKey('grants')
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode(PermissionConfig::PRM_TRANSLATION)
                    ->children()
                        ->arrayNode('subjects')
                            ->arrayPrototype()
                                ->useAttributeAsKey('name')
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                        ->arrayNode('actions')
                            ->arrayPrototype()
                                ->useAttributeAsKey('name')
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                        ->arrayNode('roles')
                            ->arrayPrototype()
                                ->useAttributeAsKey('name')
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addSettingsSection(): NodeDefinition
    {
        return (new TreeBuilder('settings'))->getRootNode()
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('app_redis')->cannotBeEmpty()->end()
                ->scalarNode('app_cache_proxy_enabled')->defaultTrue()->end()
                ->scalarNode('user_entity_class')->defaultValue('App\\Entity\\User')->end()
                ->scalarNode('user_sync_data')->defaultValue('[]')->end()
                ->scalarNode('app_entity_namespace')->defaultValue('App\\Entity')->end()
                ->scalarNode('app_value_object_namespace')->defaultValue('App\\Model\\ValueObject')->end()
                ->scalarNode('app_enum_namespace')->defaultValue('App\\Model\\Enum')->end()
                ->booleanNode('send_context_id_with_response')->defaultFalse()->end()
                ->arrayNode('unlocked_commands')
                    ->defaultValue(self::DEFAULT_UNLOCKED_COMMANDS)
                    ->validate()
                        ->ifTrue(static function (array $commands): bool {
                            foreach ($commands as $command) {
                                if (false === is_a($command, Command::class, true)) {
                                    return true;
                                }
                            }

                            return false;
                        })
                        ->thenInvalid('Invalid unlocked_commands "%s".')
                    ->end()
                    ->prototype('scalar')->end()
                ->end()
            ->end()
        ;
    }

    private function addHealthCheckSection(): NodeDefinition
    {
        return (new TreeBuilder('health_check'))->getRootNode()
            ->addDefaultsIfNotSet()
            ->canBeDisabled()
            ->children()
                ->scalarNode('mysql_table_name')->defaultValue('_doctrine_migration_versions')->end()
                ->arrayNode('mongo_collections')
                    ->defaultValue([
                        'anzu_mongo_app_log_collection',
                        'anzu_mongo_audit_log_collection',
                    ])
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('modules')
                    ->defaultValue(self::HEALTH_CHECK_MODULES)
                    ->validate()
                        ->ifTrue(static function (array $modules): bool {
                            foreach ($modules as $module) {
                                if (false === in_array($module, self::HEALTH_CHECK_MODULES, true)) {
                                    return true;
                                }
                            }

                            return false;
                        })
                        ->thenInvalid('Invalid health_check_modules "%s".')
                    ->end()
                    ->prototype('scalar')->end()
                ->end()
            ->end()
        ;
    }

    private function addErrorsSection(): NodeDefinition
    {
        return (new TreeBuilder('errors'))->getRootNode()
            ->addDefaultsIfNotSet()
            ->canBeDisabled()
            ->children()
                ->arrayNode('only_uri_match')
                    ->defaultValue([])
                    ->info('List of regexes for which are errors handled.')
                    ->prototype('scalar')->end()
                ->end()
                ->scalarNode('default_exception_handler')
                    ->cannotBeEmpty()
                    ->defaultValue(DefaultExceptionHandler::class)
                    ->validate()
                        ->ifTrue(static fn (string $handler): bool => false === is_a($handler, ExceptionHandlerInterface::class, true))
                        ->thenInvalid('Invalid unlocked_commands "%s".')
                    ->end()
                ->end()
                ->arrayNode('exception_handlers')
                    ->defaultValue(self::EXCEPTION_HANDLERS)
                    ->validate()
                        ->ifTrue(static function (array $handlers): bool {
                            foreach ($handlers as $handler) {
                                if (false === in_array($handler, self::EXCEPTION_HANDLERS, true)) {
                                    return true;
                                }
                            }

                            return false;
                        })
                        ->thenInvalid('Invalid exception_handlers "%s".')
                    ->end()
                    ->prototype('scalar')->end()
                ->end()
            ->end()
        ;
    }

    private function addLogSection(): NodeDefinition
    {
        return (new TreeBuilder('logs'))->getRootNode()
            ->addDefaultsIfNotSet()
            ->canBeDisabled()
            ->children()
                ->arrayNode('messenger_transport')
                    ->children()
                        ->scalarNode('name')->isRequired()->end()
                        ->scalarNode('dsn')->isRequired()->end()
                    ->end()
                ->end()
                ->arrayNode('app')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->append($this->addMongoConnectionSubSection('appLogs'))
                        ->arrayNode('ignored_exceptions')
                            ->defaultValue([])
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('audit')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->append($this->addMongoConnectionSubSection('auditLogs'))
                    ->end()
                    ->children()
                        ->arrayNode('logged_methods')
                            ->defaultValue([
                                Request::METHOD_POST,
                                Request::METHOD_PUT,
                                Request::METHOD_PATCH,
                                Request::METHOD_DELETE,
                            ])
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addMongoConnectionSubSection(string $collection): NodeDefinition
    {
        return (new TreeBuilder('mongo'))->getRootNode()
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('uri')->isRequired()->end()
                ->scalarNode('username')->isRequired()->end()
                ->scalarNode('password')->isRequired()->end()
                ->scalarNode('database')->isRequired()->end()
                ->scalarNode('ssl')->defaultFalse()->end()
                ->scalarNode('collection')->defaultValue($collection)->end()
            ->end()
        ;
    }

    private function addJobsSection(): NodeDefinition
    {
        return (new TreeBuilder('jobs'))->getRootNode()
            ->addDefaultsIfNotSet()
            ->children()
                ->integerNode('batch_size')->defaultValue(50)->end()
                ->integerNode('max_exec_time')->defaultValue(50)->end()
                ->integerNode('max_memory')->defaultValue(100_000_000)->end()
                ->integerNode('no_job_idle_time')->defaultValue(10)->end()
            ->end()
        ;
    }
}
