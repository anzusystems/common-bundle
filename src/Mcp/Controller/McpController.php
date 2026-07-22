<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Controller;

use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Mcp\McpRateLimiter;
use Mcp\Server;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\Http\Middleware\ProtocolVersionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class McpController
{
    private const string STREAMED_CONTENT_TYPE = 'text/event-stream';

    /**
     * @param list<string> $allowedHosts
     */
    public function __construct(
        private Server $server,
        private HttpMessageFactoryInterface $httpMessageFactory,
        private HttpFoundationFactoryInterface $httpFoundationFactory,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private McpRateLimiter $rateLimiter,
        private LoggerInterface $logger,
        private array $allowedHosts,
    ) {
    }

    public function handle(Request $request): Response
    {
        AuditLogResourceHelper::excludeFromAuditLogs($request);
        $this->rateLimiter->checkRateLimit();

        $transport = new StreamableHttpTransport(
            $this->httpMessageFactory->createRequest($request),
            $this->responseFactory,
            $this->streamFactory,
            $this->logger,
            [
                new DnsRebindingProtectionMiddleware($this->allowedHosts),
                new ProtocolVersionMiddleware(),
            ],
        );

        $psrResponse = $this->server->run($transport);
        $streamed = str_starts_with(strtolower($psrResponse->getHeaderLine('Content-Type')), self::STREAMED_CONTENT_TYPE);

        return $this->httpFoundationFactory->createResponse($psrResponse, $streamed);
    }
}
