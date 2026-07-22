<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AnzuSystems\CommonBundle\Command\CreateMcpLogCollectionCommand;
use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Log\Repository\AuditLogRepository;
use AnzuSystems\CommonBundle\Log\Repository\JournalLogRepository;
use AnzuSystems\CommonBundle\Mcp\Controller\McpController;
use AnzuSystems\CommonBundle\Mcp\Handler\StrictToolArgumentsRequestHandler;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogFinder;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogger;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogRepository;
use AnzuSystems\CommonBundle\Mcp\McpRateLimiter;
use AnzuSystems\CommonBundle\Mcp\McpToolExecutor;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpContextIdResolver;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpDateWindowResolver;
use AnzuSystems\CommonBundle\Mcp\Tool\GetLogsByContextTool;
use AnzuSystems\CommonBundle\Mcp\Tool\SearchAppLogsTool;
use AnzuSystems\CommonBundle\Mcp\Tool\SearchAuditLogsTool;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services
        ->defaults()
        ->autowire(false)
        ->autoconfigure(false)
    ;

    $services->set(McpContextIdResolver::class);

    $services->set(McpDateWindowResolver::class);

    $services->set(StrictToolArgumentsRequestHandler::class)
        ->arg('$registry', service('mcp.registry'))
        ->arg('$logger', service('logger'))
        ->tag('mcp.request_handler')
        ->tag('monolog.logger', ['channel' => 'mcp'])
    ;

    $services->set(McpLogger::class)
        ->arg('$mcpLogCollection', service('anzu_mongo_mcp_log_collection'))
    ;

    $services->set(McpLogRepository::class)
        ->arg('$mcpLogCollection', service('anzu_mongo_mcp_log_collection'))
        ->arg('$queryMaxTimeMs', param('anzu_systems_common.mongo_query_max_time_ms'))
    ;

    $services->set(McpLogFinder::class)
        ->arg('$auditLogRepository', service(AuditLogRepository::class))
        ->arg('$journalLogRepository', service(JournalLogRepository::class))
        ->arg('$mcpLogRepository', service(McpLogRepository::class))
        ->arg('$dateWindowResolver', service(McpDateWindowResolver::class))
    ;

    $services->set(McpToolExecutor::class)
        ->arg('$currentUserProvider', service(CurrentAnzuUserProvider::class))
        ->arg('$logger', service('logger'))
        ->arg('$mcpLogger', service(McpLogger::class))
        ->arg('$toolErrorExceptions', null)
        ->tag('monolog.logger', ['channel' => 'mcp'])
    ;

    $services->set(McpRateLimiter::class)
        ->arg('$mcpLimiter', service('anzu_systems_common.mcp.rate_limiter_factory'))
        ->arg('$currentUserProvider', service(CurrentAnzuUserProvider::class))
    ;

    $services->set(McpController::class)
        ->arg('$server', service('mcp.server'))
        ->arg('$httpMessageFactory', service('mcp.psr_http_factory'))
        ->arg('$httpFoundationFactory', service('mcp.http_foundation_factory'))
        ->arg('$responseFactory', service('mcp.psr17_factory'))
        ->arg('$streamFactory', service('mcp.psr17_factory'))
        ->arg('$rateLimiter', service(McpRateLimiter::class))
        ->arg('$logger', service('logger'))
        ->arg('$allowedHosts', null)
        ->tag('controller.service_arguments')
        ->tag('monolog.logger', ['channel' => 'mcp'])
        ->public()
    ;

    $services->set(CreateMcpLogCollectionCommand::class)
        ->arg('$mcpLogDatabase', service('anzu_mongo_mcp_log_database'))
        ->arg('$mcpLogCollectionName', null)
        ->arg('$mcpLogCollectionSizeMb', null)
        ->tag('console.command')
    ;

    $services->set(SearchAppLogsTool::class)
        ->arg('$logFinder', service(McpLogFinder::class))
        ->arg('$contextIdResolver', service(McpContextIdResolver::class))
        ->arg('$toolExecutor', service(McpToolExecutor::class))
        ->tag('mcp.tool')
    ;

    $services->set(SearchAuditLogsTool::class)
        ->arg('$logFinder', service(McpLogFinder::class))
        ->arg('$contextIdResolver', service(McpContextIdResolver::class))
        ->arg('$toolExecutor', service(McpToolExecutor::class))
        ->tag('mcp.tool')
    ;

    $services->set(GetLogsByContextTool::class)
        ->arg('$logFinder', service(McpLogFinder::class))
        ->arg('$contextIdResolver', service(McpContextIdResolver::class))
        ->arg('$toolExecutor', service(McpToolExecutor::class))
        ->tag('mcp.tool')
    ;
};
