<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AnzuSystems\CommonBundle\AnzuSystemsCommonBundle;
use AnzuSystems\CommonBundle\DataFixtures\FixturesLoader;
use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Event\Listener\ExceptionListener;
use AnzuSystems\CommonBundle\Event\Listener\LockReleaseListener;
use AnzuSystems\CommonBundle\Event\Subscriber\CommandLockSubscriber;
use AnzuSystems\CommonBundle\Request\ParamConverter\ApiFilterParamConverter;
use AnzuSystems\CommonBundle\Request\ParamConverter\EnumParamConverter;
use AnzuSystems\CommonBundle\Request\ParamConverter\ValueObjectParamConverter;
use AnzuSystems\CommonBundle\Util\ResourceLocker;
use AnzuSystems\CommonBundle\Validator\Constraints\EntityExistsValidator;
use AnzuSystems\CommonBundle\Validator\Constraints\NotEmptyIdValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelEvents;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services
        ->defaults()
        ->autowire(false)
        ->autoconfigure(false)
    ;

    $services->set(CurrentAnzuUserProvider::class)
        ->arg('$security', service('security.helper'))
        ->arg('$entityManager', service(EntityManagerInterface::class))
        ->arg('$userEntityClass', null)
    ;

    $services->set(ResourceLocker::class)
        ->arg('$appRedis', null)
        ->arg('$entityManager', service(EntityManagerInterface::class))
    ;

    $services->set(LockReleaseListener::class)
        ->arg('$resourceLocker', service(ResourceLocker::class))
        ->tag('kernel.event_listener', ['event' => KernelEvents::TERMINATE])
    ;

    $services->set(CommandLockSubscriber::class)
        ->arg('$appRedis', null)
        ->arg('$unlockedCommands', null)
        ->tag('kernel.event_subscriber')
    ;

    $services->set(FixturesLoader::class)
        ->arg('$fixtures', tagged_iterator(
            tag: AnzuSystemsCommonBundle::TAG_DATA_FIXTURE,
            defaultIndexMethod: 'getIndexKey',
            defaultPriorityMethod: 'getPriority',
        ))
    ;

    $services->set(ExceptionListener::class)
        ->arg('$exceptionHandlers', null)
        ->arg('$defaultExceptionHandler', null)
        ->arg('$ignoredExceptions', null)
        ->arg('$appLogger', service('monolog.logger'))
        ->arg('$logContextFactory', null)
        ->arg('$onlyUriMatch', null)
        ->tag('kernel.event_listener', ['event' => KernelEvents::EXCEPTION])
    ;

    $services->set(ApiFilterParamConverter::class)
        ->tag('request.param_converter', ['priority' => false, 'converter' => ApiFilterParamConverter::class])
    ;

    $services->set(ValueObjectParamConverter::class)
        ->tag('request.param_converter', ['priority' => false, 'converter' => ValueObjectParamConverter::class])
    ;

    $services->set(EntityExistsValidator::class)
        ->arg('$entityManager', service(EntityManagerInterface::class))
        ->tag('validator.constraint_validator')
    ;

    $services->set(NotEmptyIdValidator::class)
        ->tag('validator.constraint_validator')
    ;

    $services->set(EnumParamConverter::class)
        ->tag('request.param_converter', ['priority' => false, 'converter' => EnumParamConverter::class])
    ;
};
