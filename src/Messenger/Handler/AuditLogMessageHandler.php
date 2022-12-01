<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Messenger\Handler;

use AnzuSystems\CommonBundle\Messenger\Message\AuditLogMessage;
use Symfony\Bridge\Monolog\Logger;

final class AuditLogMessageHandler
{
    public function __construct(
        private readonly Logger $auditSyncLogger,
    ) {
    }

    public function __invoke(AuditLogMessage $logMessage): void
    {
        foreach ($this->auditSyncLogger->getHandlers() as $handler) {
            $handler->handle($logMessage->getRecord());
        }
    }
}
