<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Controller;

use AnzuSystems\CommonBundle\Helper\StringHelper;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Mcp\McpRateLimiter;
use LogicException;
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
     * @var list<string>
     */
    private array $allowedHosts;

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
        array $allowedHosts,
    ) {
        $hosts = array_values(array_filter(array_map(trim(...), $allowedHosts), StringHelper::isNotEmpty(...)));
        if ([] === $hosts) {
            throw new LogicException('MCP allowed_hosts must not be empty, every request would be rejected with 403.');
        }
        $this->allowedHosts = $hosts;
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
