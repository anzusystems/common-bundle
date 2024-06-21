<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle;

use AnzuSystems\CommonBundle\DependencyInjection\AnzuSystemsCommonExtension;
use AnzuSystems\CommonBundle\DependencyInjection\CompilerPass\ExceptionHandlerCompilerPass;
use AnzuSystems\CommonBundle\DependencyInjection\CompilerPass\HealthCheckModuleCompilerPass;
use AnzuSystems\SerializerBundle\AnzuSystemsSerializerBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class AnzuSystemsCommonBundle extends Bundle
{
    public const string TAG_HEALTH_CHECK_MODULE = 'anzu_systems_common.health_check.module';
    public const string TAG_EXCEPTION_HANDLER = 'anzu_systems_common.logs.exception_handler';
    public const string TAG_DATA_FIXTURE = 'anzu_systems_common.data_fixtures';
    public const string TAG_JOB_PROCESSOR = 'anzu_systems_common.job_processor';
    public const string TAG_SERIALIZER_HANDLER = AnzuSystemsSerializerBundle::TAG_SERIALIZER_HANDLER;

    public function build(ContainerBuilder $container): void
    {
        $container->registerExtension(new AnzuSystemsCommonExtension());
        $container->addCompilerPass(new ExceptionHandlerCompilerPass());
        $container->addCompilerPass(new HealthCheckModuleCompilerPass());
    }
}
