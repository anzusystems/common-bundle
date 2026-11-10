<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Messenger\Handler;

use AnzuSystems\CommonBundle\Messenger\Message\JournalLogMessage;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final class JournalLogMessageHandler
{
    public function __construct(
        private readonly LoggerInterface $journalSyncLogger,
    ) {
    }

    public function __invoke(JournalLogMessage $logMessage): void
    {
        if (false === ($this->journalSyncLogger instanceof Logger)) {
            return;
        }

        foreach ($this->journalSyncLogger->getHandlers() as $handler) {
            $handler->handle($logMessage->getRecord());
        }
    }
}
