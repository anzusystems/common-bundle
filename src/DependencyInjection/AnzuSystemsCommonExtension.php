<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DependencyInjection;

use AnzuSystems\CommonBundle\AnzuSystemsCommonBundle;
use AnzuSystems\CommonBundle\AnzuTap\AnzuTapBodyPostprocessor;
use AnzuSystems\CommonBundle\AnzuTap\AnzuTapBodyPreprocessor;
use AnzuSystems\CommonBundle\AnzuTap\AnzuTapEditor;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Mark\AnzuMarkTransformerInterface;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Mark\LinkNodeTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Mark\MarkNodeTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\AnchorTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\AnzuNodeTransformerInterface;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\BulletListTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\HeadingTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\HorizontalRuleTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\LineBreakTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\ListItemTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\OrderedListTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\ParagraphNodeTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\TableCellTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\TableRowTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\TableTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\TextNodeTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\XRemoveTransformer;
use AnzuSystems\CommonBundle\AnzuTap\Transformer\Node\XSkipTransformer;
use AnzuSystems\CommonBundle\AnzuTap\TransformerProvider\AnzuTapMarkNodeTransformerProvider;
use AnzuSystems\CommonBundle\AnzuTap\TransformerProvider\AnzuTapNodeTransformerProvider;
use AnzuSystems\CommonBundle\Command\SyncBaseUsersCommand;
use AnzuSystems\CommonBundle\Controller\DebugController;
use AnzuSystems\CommonBundle\Controller\HealthCheckController;
use AnzuSystems\CommonBundle\Controller\LogController;
use AnzuSystems\CommonBundle\Controller\PermissionController;
use AnzuSystems\CommonBundle\DataFixtures\Interfaces\FixturesInterface;
use AnzuSystems\CommonBundle\Doctrine\Query\AST\DateTime\Year;
use AnzuSystems\CommonBundle\Doctrine\Query\AST\Numeric\Rand;
use AnzuSystems\CommonBundle\Doctrine\Query\AST\String\Field;
use AnzuSystems\CommonBundle\Domain\Job\JobRunner;
use AnzuSystems\CommonBundle\Domain\Job\Processor\AbstractJobProcessor;
use AnzuSystems\CommonBundle\Domain\PermissionGroup\PermissionGroupFacade;
use AnzuSystems\CommonBundle\Domain\PermissionGroup\PermissionGroupManager;
use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Domain\User\UserSyncFacade;
use AnzuSystems\CommonBundle\Domain\User\UserSyncManager;
use AnzuSystems\CommonBundle\Event\Listener\ConsoleExceptionListener;
use AnzuSystems\CommonBundle\Event\Listener\ContextIdOnResponseListener;
use AnzuSystems\CommonBundle\Event\Listener\ExceptionListener;
use AnzuSystems\CommonBundle\Event\Subscriber\AuditLogSubscriber;
use AnzuSystems\CommonBundle\Event\Subscriber\CommandLockSubscriber;
use AnzuSystems\CommonBundle\Exception\Handler\AccessDeniedExceptionHandler;
use AnzuSystems\CommonBundle\Exception\Handler\AppReadOnlyModeExceptionHandler;
use AnzuSystems\CommonBundle\Exception\Handler\DefaultExceptionHandler;
use AnzuSystems\CommonBundle\Exception\Handler\ExceptionHandlerInterface;
use AnzuSystems\CommonBundle\Exception\Handler\NotFoundExceptionHandler;
use AnzuSystems\CommonBundle\Exception\Handler\ValidationExceptionHandler;
use AnzuSystems\CommonBundle\HealthCheck\HealthChecker;
use AnzuSystems\CommonBundle\HealthCheck\Module\DataMountModule;
use AnzuSystems\CommonBundle\HealthCheck\Module\ForwardIpModule;
use AnzuSystems\CommonBundle\HealthCheck\Module\ModuleInterface;
use AnzuSystems\CommonBundle\HealthCheck\Module\MongoModule;
use AnzuSystems\CommonBundle\HealthCheck\Module\MysqlModule;
use AnzuSystems\CommonBundle\HealthCheck\Module\OpCacheModule;
use AnzuSystems\CommonBundle\HealthCheck\Module\RedisModule;
use AnzuSystems\CommonBundle\Log\Factory\LogContextFactory;
use AnzuSystems\CommonBundle\Log\LogFacade;
use AnzuSystems\CommonBundle\Log\Repository\AuditLogRepository;
use AnzuSystems\CommonBundle\Log\Repository\JournalLogRepository;
use AnzuSystems\CommonBundle\Messenger\Message\AuditLogMessage;
use AnzuSystems\CommonBundle\Messenger\Message\JournalLogMessage;
use AnzuSystems\CommonBundle\Request\ParamConverter\ApiFilterParamConverter;
use AnzuSystems\CommonBundle\Request\ParamConverter\EnumParamConverter;
use AnzuSystems\CommonBundle\Request\ParamConverter\ValueObjectParamConverter;
use AnzuSystems\CommonBundle\Request\ValueResolver\ApiFilterParamValueResolver;
use AnzuSystems\CommonBundle\Request\ValueResolver\ArrayStringValueResolver;
use AnzuSystems\CommonBundle\Request\ValueResolver\ValueObjectValueResolver;
use AnzuSystems\CommonBundle\Security\PermissionConfig;
use AnzuSystems\CommonBundle\Serializer\Exception\SerializerExceptionHandler;
use AnzuSystems\CommonBundle\Serializer\Handler\Handlers\GeolocationHandler;
use AnzuSystems\CommonBundle\Serializer\Handler\Handlers\ValueObjectHandler;
use AnzuSystems\CommonBundle\Serializer\Service\BsonConverter;
use AnzuSystems\CommonBundle\Util\ResourceLocker;
use AnzuSystems\CommonBundle\Validator\Constraints\UniqueEntityDtoValidator;
use AnzuSystems\CommonBundle\Validator\Validator;
use AnzuSystems\SerializerBundle\Metadata\MetadataRegistry;
use AnzuSystems\SerializerBundle\Serializer;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use MongoDB;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class AnzuSystemsCommonExtension extends Extension implements PrependExtensionInterface
{
    private array $processedConfig;

    public function prepend(ContainerBuilder $container): void
    {
        $this->processedConfig = $this->processConfiguration(
            new Configuration(),
            $container->getExtensionConfig($this->getAlias())
        );

        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'dql' => [
                    'datetime_functions' => [
                        'year' => Year::class,
                    ],
                    'numeric_functions' => [
                        'rand' => Rand::class,
                    ],
                    'string_functions' => [
                        'field' => Field::class,
                    ],
                ],
            ],
            'dbal' => [
                'mapping_types' => [
                    'enum' => 'string',
                ],
            ],
        ]);

        $logs = $this->processedConfig['logs'];
        if (false === $logs['enabled']) {
            return;
        }

        $container->prependExtensionConfig('monolog', [
            'channels' => ['app', 'journal', 'audit', 'journal_sync', 'audit_sync'],
            'handlers' => [
                'journal' => [
                    'type' => 'service',
                    'channels' => 'journal',
                    'id' => 'anzu_systems_common.logs.journal_log_messenger_handler',
                ],
                'audit' => [
                    'type' => 'service',
                    'channels' => 'audit',
                    'id' => 'anzu_systems_common.logs.audit_log_messenger_handler',
                ],
                'journal_sync' => [
                    'type' => 'mongodb',
                    'channels' => 'journal_sync',
                    'level' => 'debug',
                    'mongodb' => [
                        'id' => 'anzu_systems_common.logs.journal_log_client',
                        'database' => $logs['journal']['mongo']['database'],
                        'collection' => $logs['journal']['mongo']['collection'],
                    ],
                ],
                'audit_sync' => [
                    'type' => 'mongodb',
                    'channels' => 'audit_sync',
                    'level' => 'debug',
                    'mongodb' => [
                        'id' => 'anzu_systems_common.logs.audit_log_client',
                        'database' => $logs['audit']['mongo']['database'],
                        'collection' => $logs['audit']['mongo']['collection'],
                    ],
                ],
            ],
        ]);

        $messengerTransport = $logs['messenger_transport'];
        $container->prependExtensionConfig('framework', [
            'messenger' => [
                'transports' => [
                    $messengerTransport['name'] => [
                        'dsn' => $messengerTransport['dsn'],
                    ],
                ],
                'routing' => [
                    JournalLogMessage::class => $messengerTransport['name'],
                    AuditLogMessage::class => $messengerTransport['name'],
                ],
            ],
        ]);
    }

    /**
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.php');

        $this->loadSettings($container);
        $this->loadHealthCheck($container);
        $this->loadErrors($container);
        $this->loadLogs($loader, $container);
        $this->loadAnzuSerializer($container);
        $this->loadPermissions($container);
        $this->loadValueResolvers($container);
        $this->loadJobs($container);
        $this->loadEditors($container);
    }

    private function loadPermissions(ContainerBuilder $container): void
    {
        $permissions = $this->processedConfig['permissions'];
        if (false === $permissions['enabled']) {
            return;
        }

        $container->setDefinition(
            PermissionGroupManager::class,
            (new Definition(PermissionGroupManager::class))
                ->setMethodCalls([
                    ['setCurrentAnzuUserProvider', [new Reference(CurrentAnzuUserProvider::class)]],
                    ['setEntityManager', [new Reference(EntityManagerInterface::class)]],
                ])
        );

        $container->setDefinition(
            PermissionGroupFacade::class,
            (new Definition(PermissionGroupFacade::class))
                ->setArgument('$validator', new Reference(Validator::class))
                ->setArgument('$permissionGroupManager', new Reference(PermissionGroupManager::class))
        );

        $container->setDefinition(
            PermissionConfig::class,
            (new Definition(PermissionConfig::class))
                ->setArgument('$config', $permissions)
        );
        $container->setDefinition(
            PermissionController::class,
            $this->createControllerDefinition(PermissionController::class, [
                '$permissionConfig' => new Reference(PermissionConfig::class),
            ])
        );
    }

    private function loadSettings(ContainerBuilder $container): void
    {
        $settings = $this->processedConfig['settings'];

        $container->setParameter('app_cache_proxy_enabled', $settings['app_cache_proxy_enabled']);

        $container
            ->getDefinition(ResourceLocker::class)
            ->replaceArgument('$appRedis', new Reference($settings['app_redis']));

        $container
            ->getDefinition(CommandLockSubscriber::class)
            ->replaceArgument('$appRedis', new Reference($settings['app_redis']))
            ->replaceArgument('$unlockedCommands', $settings['unlocked_commands']);

        $container
            ->getDefinition(CurrentAnzuUserProvider::class)
            ->replaceArgument('$userEntityClass', $settings['user_entity_class']);

        $container
            ->getDefinition(UniqueEntityDtoValidator::class)
            ->replaceArgument('$userEntityClass', $settings['user_entity_class']);

        if ($settings['send_context_id_with_response']) {
            $container->register(ContextIdOnResponseListener::class)
                ->addTag('kernel.event_listener', ['event' => KernelEvents::RESPONSE])
            ;
        }

        $definition = $this->createControllerDefinition(DebugController::class);
        $container->setDefinition(DebugController::class, $definition);

        $container
            ->registerForAutoconfiguration(FixturesInterface::class)
            ->addTag(AnzuSystemsCommonBundle::TAG_DATA_FIXTURE);

        $container
            ->registerForAutoconfiguration(AbstractJobProcessor::class)
            ->addTag(AnzuSystemsCommonBundle::TAG_JOB_PROCESSOR);

        $container->setDefinition(
            UserSyncManager::class,
            (new Definition(UserSyncManager::class))
                ->setMethodCalls([
                    ['setCurrentAnzuUserProvider', [new Reference(CurrentAnzuUserProvider::class)]],
                    ['setEntityManager', [new Reference(EntityManagerInterface::class)]],
                ])
        );

        $container->setDefinition(
            UserSyncFacade::class,
            (new Definition(UserSyncFacade::class))
                ->setArgument('$entityManager', new Reference(EntityManagerInterface::class))
                ->setArgument('$userEntityClass', $settings['user_entity_class'])
                ->setArgument('$validator', new Reference(Validator::class))
                ->setArgument('$userSyncManager', new Reference(UserSyncManager::class))
        );

        $container->getDefinition(SyncBaseUsersCommand::class)
            ->setArgument('$serializer', new Reference(Serializer::class))
            ->setArgument('$userFacade', new Reference(UserSyncFacade::class))
        ;
    }

    private function loadErrors(ContainerBuilder $container): void
    {
        $errors = $this->processedConfig['errors'];
        if (false === $errors['enabled']) {
            return;
        }

        $handlers = $errors['exception_handlers'];

        /** @psalm-var callable(class-string<ExceptionHandlerInterface>):bool $hasHandler */
        $hasHandler = static fn (string $handler): bool => in_array($handler, $handlers, true);
        $debug = $container->getParameter('kernel.environment') !== 'prod';

        if (DefaultExceptionHandler::class === $errors['default_exception_handler']) {
            $definition = new Definition(DefaultExceptionHandler::class);
            $definition->addArgument($debug);
            $container->setDefinition(DefaultExceptionHandler::class, $definition);
        }

        if ($hasHandler(AccessDeniedExceptionHandler::class)) {
            $definition = new Definition(AccessDeniedExceptionHandler::class);
            $definition->addArgument($debug);
            $definition->addTag(AnzuSystemsCommonBundle::TAG_EXCEPTION_HANDLER);
            $container->setDefinition(AccessDeniedExceptionHandler::class, $definition);
        }

        if ($hasHandler(AppReadOnlyModeExceptionHandler::class)) {
            $definition = new Definition(AppReadOnlyModeExceptionHandler::class);
            $definition->addTag(AnzuSystemsCommonBundle::TAG_EXCEPTION_HANDLER);
            $container->setDefinition(AppReadOnlyModeExceptionHandler::class, $definition);
        }

        if ($hasHandler(NotFoundExceptionHandler::class)) {
            $definition = new Definition(NotFoundExceptionHandler::class);
            $definition->addTag(AnzuSystemsCommonBundle::TAG_EXCEPTION_HANDLER);
            $container->setDefinition(NotFoundExceptionHandler::class, $definition);
        }

        if ($hasHandler(ValidationExceptionHandler::class)) {
            $definition = new Definition(ValidationExceptionHandler::class);
            $definition->addTag(AnzuSystemsCommonBundle::TAG_EXCEPTION_HANDLER);
            $container->setDefinition(ValidationExceptionHandler::class, $definition);
        }

        if ($hasHandler(SerializerExceptionHandler::class)) {
            $definition = new Definition(SerializerExceptionHandler::class);
            $definition->addArgument($debug);
            $definition->addTag(AnzuSystemsCommonBundle::TAG_EXCEPTION_HANDLER);
            $container->setDefinition(SerializerExceptionHandler::class, $definition);
        }

        $container
            ->getDefinition(ExceptionListener::class)
            ->replaceArgument('$defaultExceptionHandler', new Reference($errors['default_exception_handler']))
            ->replaceArgument('$onlyUriMatch', $errors['only_uri_match'])
        ;

        $container
            ->registerForAutoconfiguration(ExceptionHandlerInterface::class)
            ->addTag(AnzuSystemsCommonBundle::TAG_EXCEPTION_HANDLER)
        ;
    }

    private function loadHealthCheck(ContainerBuilder $container): void
    {
        $healthCheck = $this->processedConfig['health_check'];
        $modules = $healthCheck['modules'];
        if (false === $healthCheck['enabled']) {
            return;
        }

        /** @psalm-var callable(class-string<ModuleInterface>):bool $hasModule */
        $hasModule = static fn (string $module): bool => in_array($module, $modules, true);

        if ($hasModule(RedisModule::class)) {
            $definition = new Definition(RedisModule::class);
            $definition->setArgument('$appRedis', new Reference($this->processedConfig['settings']['app_redis']));
            $definition->addTag(AnzuSystemsCommonBundle::TAG_HEALTH_CHECK_MODULE);
            $container->setDefinition(RedisModule::class, $definition);
        }

        if ($hasModule(MysqlModule::class)) {
            $definition = new Definition(MysqlModule::class);
            $definition->setArgument('$connection', new Reference('database_connection'));
            $definition->setArgument('$tableName', $healthCheck['mysql_table_name']);
            $definition->addTag(AnzuSystemsCommonBundle::TAG_HEALTH_CHECK_MODULE);
            $container->setDefinition(MysqlModule::class, $definition);
        }

        if ($hasModule(OpCacheModule::class)) {
            $definition = new Definition(OpCacheModule::class);
            $definition->addTag(AnzuSystemsCommonBundle::TAG_HEALTH_CHECK_MODULE);
            $container->setDefinition(OpCacheModule::class, $definition);
        }

        if ($hasModule(ForwardIpModule::class)) {
            $definition = new Definition(ForwardIpModule::class);
            $definition->setArgument('$requestStack', new Reference('request_stack'));
            $definition->addTag(AnzuSystemsCommonBundle::TAG_HEALTH_CHECK_MODULE);
            $container->setDefinition(ForwardIpModule::class, $definition);
        }

        if ($hasModule(DataMountModule::class)) {
            $definition = new Definition(DataMountModule::class);
            $definition->addTag(AnzuSystemsCommonBundle::TAG_HEALTH_CHECK_MODULE);
            $container->setDefinition(DataMountModule::class, $definition);
        }

        if ($hasModule(MongoModule::class)) {
            $collections = array_map(
                static fn (string $collection) => new Reference($collection),
                $healthCheck['mongo_collections']
            );
            $definition = new Definition(MongoModule::class);
            $definition->setArgument('$collections', new IteratorArgument($collections));
            $definition->addTag(AnzuSystemsCommonBundle::TAG_HEALTH_CHECK_MODULE);
            $container->setDefinition(MongoModule::class, $definition);
        }

        $healthCheckerDefinition = new Definition(HealthChecker::class);
        $healthCheckerDefinition->setArgument('$requestStack', new Reference('request_stack'));
        $healthCheckerDefinition->setArgument('$logger', new Reference('monolog.logger'));
        $healthCheckerDefinition->setArgument(
            '$serializer',
            new Reference(
                Serializer::class,
                invalidBehavior: ContainerInterface::NULL_ON_INVALID_REFERENCE
            )
        );
        $container->setDefinition(HealthChecker::class, $healthCheckerDefinition);

        $definition = $this->createControllerDefinition(HealthCheckController::class, [
            '$healthChecker' => new Reference(HealthChecker::class),
        ]);
        $container->setDefinition(HealthCheckController::class, $definition);
    }

    private function loadLogs(LoaderInterface $loader, ContainerBuilder $container): void
    {
        $logs = $this->processedConfig['logs'];
        if (false === $logs['enabled']) {
            return;
        }

        $container->setParameter('anzu_systems_common.mongo_query_max_time_ms', $this->processedConfig['settings']['mongo_query_max_time_ms']);

        $loader->load('logs.php');

        $container
            ->getDefinition(AuditLogSubscriber::class)
            ->replaceArgument('$loggedMethods', $logs['audit']['logged_methods']);

        $container
            ->getDefinition(ExceptionListener::class)
            ->replaceArgument('$logContextFactory', new Reference(LogContextFactory::class))
            ->replaceArgument('$ignoredExceptions', $logs['app']['ignored_exceptions'])
        ;

        $container
            ->getDefinition(ConsoleExceptionListener::class)
            ->replaceArgument('$logContextFactory', new Reference(LogContextFactory::class))
            ->replaceArgument('$ignoredExceptions', $logs['app']['ignored_exceptions'])
        ;

        $journalLogMongo = $logs['journal']['mongo'];
        $journalLogClientDefinition = new Definition(MongoDB\Client::class);
        $journalLogClientDefinition->setArgument('$uri', $journalLogMongo['uri']);
        $journalLogClientDefinition->setArgument('$uriOptions', [
            'username' => $journalLogMongo['username'],
            'password' => $journalLogMongo['password'],
            'ssl' => $journalLogMongo['ssl'],
        ]);
        $container->setDefinition('anzu_systems_common.logs.journal_log_client', $journalLogClientDefinition);
        $container->registerAliasForArgument('anzu_systems_common.logs.journal_log_client', MongoDB\Client::class, '$journalLogClient');

        $auditLogMongo = $logs['audit']['mongo'];
        $auditLogClientDefinition = new Definition(MongoDB\Client::class);
        $auditLogClientDefinition->setArgument('$uri', $auditLogMongo['uri']);
        $auditLogClientDefinition->setArgument('$uriOptions', [
            'username' => $auditLogMongo['username'],
            'password' => $auditLogMongo['password'],
            'ssl' => $auditLogMongo['ssl'],
        ]);
        $container->setDefinition('anzu_systems_common.logs.audit_log_client', $auditLogClientDefinition);
        $container->registerAliasForArgument('anzu_systems_common.logs.audit_log_client', MongoDB\Client::class, '$auditLogClient');

        $journalLogCollectionDefinition = new Definition(MongoDB\Collection::class);
        $journalLogCollectionDefinition->setFactory([new Reference('anzu_systems_common.logs.journal_log_client'), 'selectCollection']);
        $journalLogCollectionDefinition->setArgument('$databaseName', $journalLogMongo['database']);
        $journalLogCollectionDefinition->setArgument('$collectionName', $journalLogMongo['collection']);
        $container->setDefinition('anzu_mongo_journal_log_collection', $journalLogCollectionDefinition);
        $container->registerAliasForArgument('anzu_mongo_journal_log_collection', MongoDB\Collection::class, '$journalLogCollection');

        $auditLogCollectionDefinition = new Definition(MongoDB\Collection::class);
        $auditLogCollectionDefinition->setFactory([new Reference('anzu_systems_common.logs.audit_log_client'), 'selectCollection']);
        $auditLogCollectionDefinition->setArgument('$databaseName', $auditLogMongo['database']);
        $auditLogCollectionDefinition->setArgument('$collectionName', $auditLogMongo['collection']);
        $container->setDefinition('anzu_mongo_audit_log_collection', $auditLogCollectionDefinition);
        $container->registerAliasForArgument('anzu_mongo_audit_log_collection', MongoDB\Collection::class, '$auditLogCollection');

        $definition = $this->createControllerDefinition(LogController::class, [
            '$auditLogRepo' => new Reference(AuditLogRepository::class),
            '$journalLogRepo' => new Reference(JournalLogRepository::class),
            '$logFacade' => new Reference(LogFacade::class),
        ]);
        $container->setDefinition(LogController::class, $definition);
    }

    private function loadAnzuSerializer(ContainerBuilder $container): void
    {
        $container->setDefinition(
            ValueObjectHandler::class,
            (new Definition(ValueObjectHandler::class))
                ->addTag(AnzuSystemsCommonBundle::TAG_SERIALIZER_HANDLER)
        );
        $container->setDefinition(
            GeolocationHandler::class,
            (new Definition(GeolocationHandler::class))
                ->addTag(AnzuSystemsCommonBundle::TAG_SERIALIZER_HANDLER)
        );

        $container->setDefinition(
            BsonConverter::class,
            (new Definition(BsonConverter::class))
                ->setArgument('$metadataRegistry', new Reference(MetadataRegistry::class))
        );
    }

    /**
     * @psalm-suppress UndefinedClass
     * @psalm-suppress MissingDependency
     */
    private function loadValueResolvers(ContainerBuilder $container): void
    {
        if (interface_exists(ValueResolverInterface::class)) {
            $container
                ->register(ApiFilterParamValueResolver::class)
                ->addTag('controller.argument_value_resolver', ['priority' => 150])
            ;
            $container
                ->register(ValueObjectValueResolver::class)
                ->addTag('controller.argument_value_resolver', ['priority' => 150])
            ;
            $container
                ->register(ArrayStringValueResolver::class)
                ->addTag('controller.argument_value_resolver', ['priority' => 150])
            ;
        }

        if (interface_exists(ParamConverterInterface::class)) {
            $container
                ->register(ApiFilterParamConverter::class)
                ->addTag('request.param_converter', ['priority' => false, 'converter' => ApiFilterParamConverter::class])
            ;
            $container
                ->register(ValueObjectParamConverter::class)
                ->addTag('request.param_converter', ['priority' => false, 'converter' => ValueObjectParamConverter::class])
            ;
            $container
                ->register(EnumParamConverter::class)
                ->addTag('request.param_converter', ['priority' => false, 'converter' => EnumParamConverter::class])
            ;
        }
    }

    private function loadJobs(ContainerBuilder $container): void
    {
        $jobs = $this->processedConfig['jobs'];
        $container->getDefinition(JobRunner::class)
            ->setArgument('$maxExecTime', $jobs['max_exec_time'])
            ->setArgument('$maxMemory', $jobs['max_memory'])
            ->setArgument('$noJobIdleTime', $jobs['no_job_idle_time'])
        ;
    }

    private function loadEditors(ContainerBuilder $container): void
    {
        $editors = $this->processedConfig['editors'];

        $definition = new Definition(AnzuTapBodyPreprocessor::class);
        $container->setDefinition(AnzuTapBodyPreprocessor::class, $definition);

        $definition = new Definition(AnzuTapBodyPostprocessor::class);
        $container->setDefinition(AnzuTapBodyPostprocessor::class, $definition);

        // MarkTransformerProviderInterface
        $definition = new Definition(AnzuTapMarkNodeTransformerProvider::class);
        $container->setDefinition(AnzuTapMarkNodeTransformerProvider::class, $definition);

        // MarkTransformerProviderInterface
        $definition = new Definition(AnzuTapNodeTransformerProvider::class);
        $container->setDefinition(AnzuTapNodeTransformerProvider::class, $definition);

        // AnzuMarkTransformerInterface
        $definition = new Definition(LinkNodeTransformer::class);
        $container->setDefinition(LinkNodeTransformer::class, $definition);

        $definition = new Definition(MarkNodeTransformer::class);
        $container->setDefinition(MarkNodeTransformer::class, $definition);

        // AnzuNodeTransformerInterface
        $definition = new Definition(XSkipTransformer::class);
        $container->setDefinition(XSkipTransformer::class, $definition);

        $definition = new Definition(XRemoveTransformer::class);
        $container->setDefinition(XRemoveTransformer::class, $definition);

        $definition = new Definition(AnchorTransformer::class);
        $container->setDefinition(AnchorTransformer::class, $definition);

        $definition = new Definition(BulletListTransformer::class);
        $container->setDefinition(BulletListTransformer::class, $definition);

        $definition = new Definition(HeadingTransformer::class);
        $container->setDefinition(HeadingTransformer::class, $definition);

        $definition = new Definition(HorizontalRuleTransformer::class);
        $container->setDefinition(HorizontalRuleTransformer::class, $definition);

        $definition = new Definition(LineBreakTransformer::class);
        $container->setDefinition(LineBreakTransformer::class, $definition);

        $definition = new Definition(ListItemTransformer::class);
        $container->setDefinition(ListItemTransformer::class, $definition);

        $definition = new Definition(OrderedListTransformer::class);
        $container->setDefinition(OrderedListTransformer::class, $definition);

        $definition = new Definition(ParagraphNodeTransformer::class);
        $container->setDefinition(ParagraphNodeTransformer::class, $definition);

        $definition = new Definition(TableCellTransformer::class);
        $container->setDefinition(TableCellTransformer::class, $definition);

        $definition = new Definition(TableRowTransformer::class);
        $container->setDefinition(TableRowTransformer::class, $definition);

        $definition = new Definition(TableTransformer::class);
        $container->setDefinition(TableTransformer::class, $definition);

        $definition = new Definition(TextNodeTransformer::class);
        $container->setDefinition(TextNodeTransformer::class, $definition);

        foreach ($editors as $editorName => $editorConfig) {
            $definition = new Definition(AnzuTapEditor::class);
            $definition->setArgument('$transformerProvider', new Reference($editorConfig[Configuration::EDITOR_NODE_TRANSFORMER_PROVIDER_CLASS]));
            $definition->setArgument('$markTransformerProvider', new Reference($editorConfig[Configuration::EDITOR_MARK_TRANSFORMER_PROVIDER_CLASS]));
            $definition->setArgument('$defaultTransformer', new Reference($editorConfig[Configuration::EDITOR_NODE_DEFAULT_TRANSFORMER_CLASS]));
            $definition->setArgument('$preprocessor', new Reference($editorConfig[Configuration::EDITOR_BODY_PREPROCESSOR]));
            $definition->setArgument('$postprocessor', new Reference($editorConfig[Configuration::EDITOR_BODY_POSTPROCESSOR]));

            $allowedNodeTransformers = [];
            /** @var class-string<AnzuNodeTransformerInterface> $serviceName */
            foreach ($editorConfig[Configuration::EDITOR_ALLOWED_NODE_TRANSFORMERS] ?? [] as $serviceName) {
                foreach ($serviceName::getSupportedNodeNames() as $supportedNodeName) {
                    $allowedNodeTransformers[$supportedNodeName] = new Reference($serviceName);
                }
            }

            foreach ($editorConfig[Configuration::EDITOR_REMOVE_NODES] ?? [] as $nodeName) {
                $allowedNodeTransformers[$nodeName] = new Reference(XRemoveTransformer::class);
            }

            foreach ($editorConfig[Configuration::EDITOR_SKIP_NODES] ?? [] as $nodeName) {
                $allowedNodeTransformers[$nodeName] = new Reference(XSkipTransformer::class);
            }

            $allowedMarkTransformers = [];
            /** @var class-string<AnzuMarkTransformerInterface> $serviceName */
            foreach ($editorConfig[Configuration::EDITOR_ALLOWED_MARK_TRANSFORMERS] ?? [] as $serviceName) {
                foreach ($serviceName::getSupportedNodeNames() as $supportedNodeName) {
                    $allowedMarkTransformers[$supportedNodeName] = new Reference($serviceName);
                }
            }

            $definition
                ->setArgument('$resolvedNodeTransformers', new ServiceLocatorArgument($allowedNodeTransformers))
                ->setArgument('$resolvedMarkTransformers', new ServiceLocatorArgument($allowedMarkTransformers))
            ;

            $container->setDefinition(sprintf('%s $%sEditor', AnzuTapEditor::class, $editorName), $definition);
            $container->setDefinition(sprintf('anzu_systems_common.editor.%s', $editorName), $definition);
        }
    }

    private function createControllerDefinition(string $class, array $arguments = []): Definition
    {
        $definition = new Definition($class);
        foreach ($arguments as $name => $argument) {
            $definition->setArgument($name, $argument);
        }
        $definition->addMethodCall('setContainer', [new Reference('service_container')]);
        $definition->addMethodCall('setSerializer', [new Reference(Serializer::class)]);
        $definition->addMethodCall('setResourceLocker', [new Reference(ResourceLocker::class)]);
        $definition->addTag('controller.service_arguments');
        $definition->addTag('container.service_subscriber');

        return $definition;
    }
}
