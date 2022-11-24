<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Event\Subscriber\AuditLogSubscriber;
use AnzuSystems\CommonBundle\Log\Factory\LogContextFactory;
use AnzuSystems\CommonBundle\Log\LogFacade;
use AnzuSystems\CommonBundle\Log\Repository\AppLogRepository;
use AnzuSystems\CommonBundle\Log\Repository\AuditLogRepository;
use AnzuSystems\CommonBundle\Messenger\Handler\AppLogMessageHandler;
use AnzuSystems\CommonBundle\Messenger\Handler\AuditLogMessageHandler;
use AnzuSystems\CommonBundle\Messenger\Message\AppLogMessage;
use AnzuSystems\CommonBundle\Messenger\Message\AuditLogMessage;
use AnzuSystems\CommonBundle\Messenger\Middleware\ContextIdentityMiddleware;
use AnzuSystems\CommonBundle\Messenger\MonologHandler\MessengerHandler;
use AnzuSystems\CommonBundle\Serializer\Service\BsonConverter;
use AnzuSystems\SerializerBundle\Metadata\MetadataRegistry;
use AnzuSystems\SerializerBundle\Serializer;
use Symfony\Component\Messenger\MessageBusInterface;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services
        ->defaults()
        ->autowire(false)
        ->autoconfigure(false)
    ;

    $services->set(LogContextFactory::class)
        ->arg('$userProvider', service(CurrentAnzuUserProvider::class))
        ->arg('$serializer', service(Serializer::class))
    ;

    $services->set(LogFacade::class)
        ->arg('$appLogger', service('monolog.logger'))
        ->arg('$logContextFactory', service(LogContextFactory::class))
        ->arg('$serializer', service(Serializer::class))
    ;

    $services->set(AuditLogSubscriber::class)
        ->arg('$auditLogger', service('monolog.logger.audit'))
        ->arg('$logContextFactory', service(LogContextFactory::class))
        ->arg('$loggedMethods', null)
        ->tag('kernel.event_subscriber')
    ;

    $services->set(BsonConverter::class)
        ->arg('$metadataRegistry', service(MetadataRegistry::class))
    ;

    $services->set(AppLogRepository::class)
        ->arg('$appLogCollection', service('anzu_mongo_app_log_collection'))
        ->arg('$serializer', service(Serializer::class))
        ->arg('$bsonConverter', service(BsonConverter::class))
    ;

    $services->set(AuditLogRepository::class)
        ->arg('$auditLogCollection', service('anzu_mongo_audit_log_collection'))
        ->arg('$serializer', service(Serializer::class))
        ->arg('$bsonConverter', service(BsonConverter::class))
    ;

    $services->set(AuditLogMessageHandler::class)
        ->arg('$auditSyncLogger', service('monolog.logger.audit_sync'))
        ->tag('messenger.message_handler', ['handler' => AuditLogMessage::class])
    ;

    $services->set(AppLogMessageHandler::class)
        ->arg('$appSyncLogger', service('monolog.logger.app_sync'))
        ->tag('messenger.message_handler', ['handler' => AppLogMessage::class])
    ;

    $services->set('anzu_systems_common.logs.app_log_messenger_handler', MessengerHandler::class)
        ->arg('$messageClass', AppLogMessage::class)
        ->arg('$messageBus', service(MessageBusInterface::class))
    ;

    $services->set('anzu_systems_common.logs.audit_log_messenger_handler', MessengerHandler::class)
        ->arg('$messageClass', AuditLogMessage::class)
        ->arg('$messageBus', service(MessageBusInterface::class))
    ;

    $services->set(ContextIdentityMiddleware::class);
};
