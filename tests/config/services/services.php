<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AnzuSystems\CommonBundle\AnzuSystemsCommonBundle;
use AnzuSystems\CommonBundle\DataFixtures\FixturesLoader;
use AnzuSystems\CommonBundle\Tests\AnzuTap\TestAnzuTapEditor;
use AnzuSystems\CommonBundle\Tests\Data\Controller\DummyController;
use AnzuSystems\CommonBundle\Tests\Data\Controller\JobController;
use AnzuSystems\CommonBundle\Tests\Data\Domain\Job\Processor\JobUserDataDeleteProcessor;
use AnzuSystems\CommonBundle\Tests\Data\Fixtures\ExampleFixtures;
use AnzuSystems\CommonBundle\Tests\Data\Fixtures\UserFixtures;
use AnzuSystems\CommonBundle\Tests\Data\Repository\UserRepository;
use AnzuSystems\CommonBundle\Util\ResourceLocker;
use AnzuSystems\SerializerBundle\Serializer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Redis;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->alias(ResourceLocker::class . '.test', ResourceLocker::class);

    $services->set('TestRedis', Redis::class)
        ->call('connect', [env('string:REDIS_HOST'), env('int:REDIS_PORT')])
        ->call('select', [env('int:REDIS_DB')])
        ->call('setOption', [Redis::OPT_PREFIX, 'common_bundle_' . env('string:APP_ENV')])
    ;

    $services->set(DummyController::class)
        ->autowire(true)
        ->autoconfigure(true)
        ->call('setSerializer', [service(Serializer::class)])
        ->call('setResourceLocker', [service(ResourceLocker::class)])
    ;

    $services->set(JobController::class)
        ->autowire(true)
        ->autoconfigure(true)
        ->call('setSerializer', [service(Serializer::class)])
        ->call('setResourceLocker', [service(ResourceLocker::class)])
    ;

    $services->alias('security.token_storage.test', 'security.untracked_token_storage');

    $services->set(UserFixtures::class)
        ->call('setEntityManager', [service(EntityManagerInterface::class)])
        ->tag(AnzuSystemsCommonBundle::TAG_DATA_FIXTURE)
    ;

    $services->set(ExampleFixtures::class)
        ->call('setEntityManager', [service(EntityManagerInterface::class)])
        ->tag(AnzuSystemsCommonBundle::TAG_DATA_FIXTURE)
    ;

    $services->set(JobUserDataDeleteProcessor::class)
        ->tag(AnzuSystemsCommonBundle::TAG_JOB_PROCESSOR)
    ;

    $services->set(UserRepository::class)
        ->arg('$registry', service(ManagerRegistry::class))
        ->tag('doctrine.repository_service')
    ;

    $services->alias(FixturesLoader::class . '.test', FixturesLoader::class)
        ->public()
    ;

    $services->set('security.authorization_checker', AuthorizationChecker::class)
        ->args([service('security.token_storage'), service('security.access.decision_manager')])
        ->public()
    ;

    $configurator->services()->set(TestAnzuTapEditor::class)
        ->autowire()
        ->public();
};
