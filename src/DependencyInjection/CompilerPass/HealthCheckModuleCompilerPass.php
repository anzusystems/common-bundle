<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\AnzuSystemsCommonBundle;
use AnzuSystems\CommonBundle\HealthCheck\HealthChecker;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class HealthCheckModuleCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (false === $container->hasDefinition(HealthChecker::class)) {
            return;
        }

        $moduleReferences = [];
        foreach (array_keys($container->findTaggedServiceIds(AnzuSystemsCommonBundle::TAG_HEALTH_CHECK_MODULE)) as $service) {
            $moduleReferences[] = new Reference($service);
        }

        $container
            ->getDefinition(HealthChecker::class)
            ->setArgument('$modules', new IteratorArgument($moduleReferences))
        ;
    }
}
