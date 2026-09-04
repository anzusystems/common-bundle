<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Controller;

use AnzuSystems\CommonBundle\Helper\StringHelper;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Mcp\McpRateLimiter;
use LogicException;
use Mcp\Server;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\AI\McpBundle\Http\MiddlewareFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class McpController
{
    private const string STREAMED_CONTENT_TYPE = 'text/event-stream';
    private const int SINGLE_MESSAGE = 1;

    private MiddlewareFactory $middlewareFactory;

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
        $this->middlewareFactory = new MiddlewareFactory($hosts);
    }

    public function handle(Request $request): Response
    {
        AuditLogResourceHelper::excludeFromAuditLogs($request);
        $this->rateLimiter->checkRateLimit($this->resolveMessageCount($request));

        $transport = new StreamableHttpTransport(
            $this->httpMessageFactory->createRequest($request),
            $this->responseFactory,
            $this->streamFactory,
            $this->logger,
            $this->middlewareFactory->create(),
        );

        $psrResponse = $this->server->run($transport);
        $streamed = str_starts_with(strtolower($psrResponse->getHeaderLine('Content-Type')), self::STREAMED_CONTENT_TYPE);

        return $this->httpFoundationFactory->createResponse($psrResponse, $streamed);
    }

    private function resolveMessageCount(Request $request): int
    {
        $content = (string) $request->getContent();
        if (StringHelper::isEmpty($content)) {
            return self::SINGLE_MESSAGE;
        }
        $decoded = json_decode($content, true);
        if (false === is_array($decoded) || false === array_is_list($decoded)) {
            return self::SINGLE_MESSAGE;
        }

        return max(self::SINGLE_MESSAGE, count($decoded));
    }
}
