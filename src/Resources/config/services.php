<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AnzuSystems\CommonBundle\AnzuSystemsCommonBundle;
use AnzuSystems\CommonBundle\Command\GenerateFixturesCommand;
use AnzuSystems\CommonBundle\Command\ProcessJobCommand;
use AnzuSystems\CommonBundle\DataFixtures\FixturesLoader;
use AnzuSystems\CommonBundle\Domain\Job\JobFacade;
use AnzuSystems\CommonBundle\Domain\Job\JobManager;
use AnzuSystems\CommonBundle\Domain\Job\JobProcessor;
use AnzuSystems\CommonBundle\Domain\Job\JobRunner;
use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Event\Listener\ConsoleExceptionListener;
use AnzuSystems\CommonBundle\Event\Listener\ExceptionListener;
use AnzuSystems\CommonBundle\Event\Listener\LockReleaseListener;
use AnzuSystems\CommonBundle\Event\Subscriber\CommandLockSubscriber;
use AnzuSystems\CommonBundle\Repository\JobRepository;
use AnzuSystems\CommonBundle\Repository\JobUserDataDeleteRepository;
use AnzuSystems\CommonBundle\Util\ResourceLocker;
use AnzuSystems\CommonBundle\Validator\Constraints\EntityExistsValidator;
use AnzuSystems\CommonBundle\Validator\Constraints\NotEmptyIdValidator;
use AnzuSystems\CommonBundle\Validator\Constraints\UniqueEntityDtoValidator;
use AnzuSystems\CommonBundle\Validator\Constraints\UniqueEntityValidator;
use AnzuSystems\CommonBundle\Validator\Validator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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
            defaultPriorityMethod: 'getPriority',
        ))
    ;

    $services->set(GenerateFixturesCommand::class)
        ->arg('$fixturesLoader', service(FixturesLoader::class))
        ->tag('console.command')
    ;

    $services->set(JobProcessor::class)
        ->arg('$processorProvider', tagged_locator(
            tag: AnzuSystemsCommonBundle::TAG_JOB_PROCESSOR,
            defaultIndexMethod: 'getSupportedJob',
        ))
    ;

    $services->set(JobRunner::class)
        ->arg('$jobRepo', service(JobRepository::class))
        ->arg('$jobProcessor', service(JobProcessor::class))
        ->arg('$entityManager', service(EntityManagerInterface::class))
    ;

    $services->set(ProcessJobCommand::class)
        ->arg('$jobRunner', service(JobRunner::class))
        ->tag('console.command')
    ;

    $services->set(JobRepository::class)
        ->arg('$registry', service(ManagerRegistry::class))
        ->tag('doctrine.repository_service')
    ;

    $services->set(JobUserDataDeleteRepository::class)
        ->arg('$registry', service(ManagerRegistry::class))
        ->tag('doctrine.repository_service')
    ;

    $services->set(JobManager::class)
        ->call('setCurrentAnzuUserProvider', [service(CurrentAnzuUserProvider::class)])
        ->call('setEntityManager', [service(EntityManagerInterface::class)])
    ;

    $services->set(JobFacade::class)
        ->arg('$validator', service(Validator::class))
        ->arg('$manager', service(JobManager::class))
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

    $services->set(ConsoleExceptionListener::class)
        ->arg('$ignoredExceptions', null)
        ->arg('$appLogger', service('monolog.logger'))
        ->arg('$logContextFactory', null)
        ->tag('kernel.event_listener', ['event' => ConsoleEvents::ERROR])
    ;

    $services->set(Validator::class)
        ->arg('$validator', service(ValidatorInterface::class))
    ;

    $services->set(EntityExistsValidator::class)
        ->arg('$entityManager', service(EntityManagerInterface::class))
        ->tag('validator.constraint_validator')
    ;

    $services->set(UniqueEntityValidator::class)
        ->arg('$propertyAccessor', service(PropertyAccessorInterface::class))
        ->arg('$entityManager', service(EntityManagerInterface::class))
        ->tag('validator.constraint_validator')
    ;

    $services->set(UniqueEntityDtoValidator::class)
        ->arg('$propertyAccessor', service(PropertyAccessorInterface::class))
        ->arg('$entityManager', service(EntityManagerInterface::class))
        ->tag('validator.constraint_validator')
    ;

    $services->set(NotEmptyIdValidator::class)
        ->tag('validator.constraint_validator')
    ;
};
