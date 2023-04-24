<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Event\Listener;

use AnzuSystems\CommonBundle\Kernel\AnzuKernel;
use AnzuSystems\Contracts\AnzuApp;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

final class ContextIdOnResponseListener
{
    public function __invoke(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        if ($response->headers->has(AnzuKernel::CONTEXT_IDENTITY_HEADER)) {
            return;
        }

        $response->headers->set(AnzuKernel::CONTEXT_IDENTITY_HEADER, AnzuApp::getContextId());
    }
}
