<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle;

use AnzuSystems\CommonBundle\DependencyInjection\AnzuSystemsCommonExtension;
use AnzuSystems\CommonBundle\DependencyInjection\CompilerPass\AnzuTapEditorCompilerPass;
use AnzuSystems\CommonBundle\DependencyInjection\CompilerPass\ExceptionHandlerCompilerPass;
use AnzuSystems\CommonBundle\DependencyInjection\CompilerPass\HealthCheckModuleCompilerPass;
use AnzuSystems\SerializerBundle\AnzuSystemsSerializerBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class AnzuSystemsCommonBundle extends Bundle
{
    public const TAG_HEALTH_CHECK_MODULE = 'anzu_systems_common.health_check.module';
    public const TAG_EXCEPTION_HANDLER = 'anzu_systems_common.logs.exception_handler';
    public const TAG_DATA_FIXTURE = 'anzu_systems_common.data_fixtures';
    public const TAG_JOB_PROCESSOR = 'anzu_systems_common.job_processor';
    public const TAG_EDITOR_MARK_TRANSFORMER = 'anzu_systems_common.editor_transformer.mark_transformer';
    public const TAG_EDITOR_NODE_TRANSFORMER = 'anzu_systems_common.editor_transformer.node_transformer';
    public const TAG_EDITOR_NODE_TRANSFORMER_PROVIDER = 'anzu_systems_common.editor_provider.node_transformer_provider';
    public const TAG_EDITOR_MARK_TRANSFORMER_PROVIDER = 'anzu_systems_common.editor_provider.mark_transformer_provider';
    public const TAG_SERIALIZER_HANDLER = AnzuSystemsSerializerBundle::TAG_SERIALIZER_HANDLER;

    public function build(ContainerBuilder $container): void
    {
        $extension = (new AnzuSystemsCommonExtension());

        $container->registerExtension($extension);
        $container->addCompilerPass(new ExceptionHandlerCompilerPass());
        $container->addCompilerPass(new HealthCheckModuleCompilerPass());
        $container->addCompilerPass(new AnzuTapEditorCompilerPass($extension));
    }
}
