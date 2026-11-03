<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Event\Listener;

use AnzuSystems\CommonBundle\Mcp\Resolver\McpHostResolver;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class McpHostRouteListener
{
    public const string ALLOWED_ON_MCP_HOST = '_mcp_host_allowed';
    public const int PRIORITY = 24;

    public function __construct(
        private McpHostResolver $mcpHostResolver,
        private bool $mcpPod,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (false === $event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        if (true === $request->attributes->get(self::ALLOWED_ON_MCP_HOST)) {
            return;
        }
        if ($this->mcpPod || $this->mcpHostResolver->isMcpHost($request)) {
            throw new NotFoundHttpException();
        }
    }
}
