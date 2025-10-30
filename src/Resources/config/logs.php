<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Event\Subscriber\AuditLogSubscriber;
use AnzuSystems\CommonBundle\Log\Factory\LogContextFactory;
use AnzuSystems\CommonBundle\Log\LogFacade;
use AnzuSystems\CommonBundle\Messenger\Message\AppLogMessage;
use AnzuSystems\CommonBundle\Messenger\Message\AuditLogMessage;
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

    $services->set(AbstractAnzuMongoRepository::class)
        ->abstract()
        ->arg('$serializer', service(Serializer::class))
        ->arg('$bsonConverter', service(BsonConverter::class))
        ->arg('$queryMaxTimeMs', param('anzu_systems_common.mongo_query_max_time_ms'))
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
