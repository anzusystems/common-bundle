<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Messenger\Handler;

use AnzuSystems\CommonBundle\Messenger\Message\AppLogMessage;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final class AppLogMessageHandler
{
    public function __construct(
        private readonly LoggerInterface $appSyncLogger,
    ) {
    }

    public function __invoke(AppLogMessage $logMessage): void
    {
        if (false === ($this->appSyncLogger instanceof Logger)) {
            return;
        }

        foreach ($this->appSyncLogger->getHandlers() as $handler) {
            /**
             * @psalm-suppress PossiblyInvalidArgument - It gives array for monolog 2, LogRecord for monolog 3.
             */
            $handler->handle($logMessage->getRecord());
        }
    }
}
