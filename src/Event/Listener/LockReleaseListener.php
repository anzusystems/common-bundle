<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Event\Listener;

use AnzuSystems\CommonBundle\Util\ResourceLocker;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

final class LockReleaseListener
{
    public function __construct(
        private readonly ResourceLocker $resourceLocker,
    ) {
    }

    public function __invoke(TerminateEvent $args): void
    {
        $this->resourceLocker->unlockAll();
    }
}
