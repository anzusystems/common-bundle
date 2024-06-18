<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\HealthCheck\Module;

use AnzuSystems\Contracts\AnzuApp;
use Symfony\Component\HttpFoundation\RequestStack;

final class ForwardIpModule implements ModuleInterface
{
    private const string INTERNAL_USER_AGENT_PREFIX = 'kube-probe/';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getName(): string
    {
        return 'forwardIp';
    }

    public function isHealthy(): bool
    {
        $currentRequest = $this->requestStack->getCurrentRequest();
        $userAgent = (string) $currentRequest?->headers->get('User-Agent');

        // In case of empty HTTP_X_FORWARDED_FOR and with User-Agent starting with a kubernetes probe header,
        // mark ip forward as correctly resolved. Kubernetes doesn't send HTTP_X_FORWARDED_FOR at all.
        if ('' === AnzuApp::getClientIp() && str_starts_with($userAgent, self::INTERNAL_USER_AGENT_PREFIX)) {
            return true;
        }

        return AnzuApp::getClientIp() === $this->requestStack->getCurrentRequest()?->getClientIp();
    }
}
