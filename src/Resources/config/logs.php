<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Event\Subscriber\AuditLogSubscriber;
use AnzuSystems\CommonBundle\Log\Factory\LogContextFactory;
use AnzuSystems\CommonBundle\Log\LogFacade;
use AnzuSystems\CommonBundle\Log\Repository\AuditLogRepository;
use AnzuSystems\CommonBundle\Log\Repository\JournalLogRepository;
use AnzuSystems\CommonBundle\Messenger\Handler\AuditLogMessageHandler;
use AnzuSystems\CommonBundle\Messenger\Handler\JournalLogMessageHandler;
use AnzuSystems\CommonBundle\Messenger\Message\AuditLogMessage;
use AnzuSystems\CommonBundle\Messenger\Message\JournalLogMessage;
use AnzuSystems\CommonBundle\Messenger\Middleware\ContextIdentityMiddleware;
use AnzuSystems\CommonBundle\Messenger\MonologHandler\MessengerHandler;
use AnzuSystems\CommonBundle\Monolog\ContextProcessor;
use AnzuSystems\CommonBundle\Repository\Mongo\AbstractAnzuMongoRepository;
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

    $services->set(ContextProcessor::class)
        ->tag('monolog.processor');

    $services->set(LogContextFactory::class)
        ->arg('$userProvider', service(CurrentAnzuUserProvider::class))
        ->arg('$serializer', service(Serializer::class))
    ;

    $services->set(LogFacade::class)
        ->arg('$journalLogger', service('monolog.logger.journal'))
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

    $services->set(AbstractAnzuMongoRepository::class)
        ->abstract()
        ->arg('$serializer', service(Serializer::class))
        ->arg('$bsonConverter', service(BsonConverter::class))
        ->arg('$queryMaxTimeMs', param('anzu_systems_common.mongo_query_max_time_ms'))
    ;

    $services->set(JournalLogRepository::class)
        ->parent(AbstractAnzuMongoRepository::class)
        ->arg('$journalLogCollection', service('anzu_mongo_journal_log_collection'))
    ;

    $services->set(AuditLogRepository::class)
        ->parent(AbstractAnzuMongoRepository::class)
        ->arg('$auditLogCollection', service('anzu_mongo_audit_log_collection'))
    ;

    $services->set(AuditLogMessageHandler::class)
        ->arg('$auditSyncLogger', service('monolog.logger.audit_sync'))
        ->tag('messenger.message_handler', ['handler' => AuditLogMessage::class])
    ;

    $services->set(JournalLogMessageHandler::class)
        ->arg('$journalSyncLogger', service('monolog.logger.journal_sync'))
        ->tag('messenger.message_handler', ['handler' => JournalLogMessage::class])
    ;

    $services->set('anzu_systems_common.logs.journal_log_messenger_handler', MessengerHandler::class)
        ->arg('$messageClass', JournalLogMessage::class)
        ->arg('$messageBus', service(MessageBusInterface::class))
    ;

    $services->set('anzu_systems_common.logs.audit_log_messenger_handler', MessengerHandler::class)
        ->arg('$messageClass', AuditLogMessage::class)
        ->arg('$messageBus', service(MessageBusInterface::class))
    ;

    $services->set(ContextIdentityMiddleware::class);
};
