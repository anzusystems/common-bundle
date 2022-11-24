<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\AnzuSystemsCommonBundle;
use AnzuSystems\CommonBundle\Event\Listener\ExceptionListener;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class ExceptionHandlerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (false === $container->hasDefinition(ExceptionListener::class)) {
            return;
        }

        $references = [];
        foreach (array_keys($container->findTaggedServiceIds(AnzuSystemsCommonBundle::TAG_EXCEPTION_HANDLER)) as $service) {
            $references[] = new Reference($service);
        }

        $container
            ->getDefinition(ExceptionListener::class)
            ->replaceArgument('$exceptionHandlers', new IteratorArgument($references))
        ;
    }
}
