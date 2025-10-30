<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Monolog;

use AnzuSystems\Contracts\AnzuApp;

use Monolog\LogRecord;

final class ContextProcessor
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $record->extra['contextId'] = AnzuApp::getContextId();

        return $record;
    }
}
