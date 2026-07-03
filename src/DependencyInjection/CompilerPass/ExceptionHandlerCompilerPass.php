<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\AnzuSystemsCommonBundle;
use AnzuSystems\CommonBundle\Event\Listener\ExceptionListener;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ExceptionHandlerCompilerPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        if (false === $container->hasDefinition(ExceptionListener::class)) {
            return;
        }

        $container
            ->getDefinition(ExceptionListener::class)
            ->replaceArgument(
                '$exceptionHandlers',
                new IteratorArgument($this->findAndSortTaggedServices(AnzuSystemsCommonBundle::TAG_EXCEPTION_HANDLER, $container))
            )
        ;
    }
}
