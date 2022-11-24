<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Messenger\Handler;

use AnzuSystems\CommonBundle\Messenger\Message\AppLogMessage;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

final class AppLogMessageHandler implements MessageHandlerInterface
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
