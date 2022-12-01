<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Messenger\Handler;

use AnzuSystems\CommonBundle\Messenger\Message\AppLogMessage;
use Symfony\Bridge\Monolog\Logger;

final class AppLogMessageHandler
{
    public function __construct(
        private readonly Logger $appSyncLogger,
    ) {
    }

    public function __invoke(AppLogMessage $logMessage): void
    {
        foreach ($this->appSyncLogger->getHandlers() as $handler) {
            $handler->handle($logMessage->getRecord());
        }
    }
}
