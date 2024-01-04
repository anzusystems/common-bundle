<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Messenger\Handler;

use AnzuSystems\CommonBundle\Messenger\Message\AuditLogMessage;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final class AuditLogMessageHandler
{
    public function __construct(
        private readonly LoggerInterface $auditSyncLogger,
    ) {
    }

    public function __invoke(AuditLogMessage $logMessage): void
    {
        if (false === ($this->auditSyncLogger instanceof Logger)) {
            return;
        }

        foreach ($this->auditSyncLogger->getHandlers() as $handler) {
            /**
             * @psalm-suppress PossiblyInvalidArgument - It gives array for monolog 2, LogRecord for monolog 3.
             */
            $handler->handle($logMessage->getRecord());
        }
    }
}
