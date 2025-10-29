<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AnzuSystems\CommonBundle\Monolog\ContextProcessor;
use AnzuSystems\CommonBundle\Monolog\IgnoreExceptionProcessor;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services
        ->defaults()
        ->autowire(false)
        ->autoconfigure(false)
    ;

    $services->set(ContextProcessor::class)
        ->tag('monolog.processor');

    $services->set(IgnoreExceptionProcessor::class)
        ->arg('$ignoredExceptions', [])
        ->tag('monolog.processor');
};
