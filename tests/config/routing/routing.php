<?php

declare(strict_types=1);

namespace Symfony\Component\Routing\Loader\Configurator;

use AnzuSystems\CommonBundle\Controller\DebugController;
use AnzuSystems\CommonBundle\Controller\HealthCheckController;
use AnzuSystems\CommonBundle\Controller\LogController;
use Symfony\Component\HttpFoundation\Request;

return static function (RoutingConfigurator $routes): void {
    $routes->add('anzu_systems_common.health_check', '/health')
        ->methods([Request::METHOD_GET])
        ->controller([HealthCheckController::class, 'healthCheck'])
    ;

    $routes->add('anzu_systems_common.logs.create', '/log')
        ->methods([Request::METHOD_POST])
        ->controller([LogController::class, 'create'])
    ;

    $routes->add('anzu_systems_common.logs.journal_list', '/log/journal') // or '/log/app' for BC compatibility
        ->methods([Request::METHOD_GET])
        ->controller([LogController::class, 'getJournalLogs'])
    ;

    $routes->add('anzu_systems_common.logs.journal_get_one', '/log/journal/{id}') // or '/log/app/{id}' for BC compatibility
        ->methods([Request::METHOD_GET])
        ->controller([LogController::class, 'getOneJournalLog'])
    ;

    $routes->add('anzu_systems_common.logs.audit_list', '/log/audit')
        ->methods([Request::METHOD_GET])
        ->controller([LogController::class, 'getAuditLogs'])
    ;

    $routes->add('anzu_systems_common.logs.audit_get_one', '/log/audit/{id}')
        ->methods([Request::METHOD_GET])
        ->controller([LogController::class, 'getOneAuditLog'])
    ;

    $routes->add('anzu_systems_common.debug.opcache', '/debug/opcache')
        ->methods([Request::METHOD_GET])
        ->controller([DebugController::class, 'opcacheStatus'])
    ;

    $routes->add('anzu_systems_common.debug.ip', '/debug/ip')
        ->methods([Request::METHOD_GET])
        ->controller([DebugController::class, 'ipCheck'])
    ;

    $routes->add('anzu_systems_common.debug.error', '/debug/error')
        ->methods([Request::METHOD_GET])
        ->controller([DebugController::class, 'error'])
    ;

    $routes->add('anzu_systems_common.debug.lead_time', '/debug/lead-time')
        ->methods([Request::METHOD_GET])
        ->controller([DebugController::class, 'getLeadTime'])
    ;

    $routes->import(resource: '../../data/Controller', type: 'attribute');
};
