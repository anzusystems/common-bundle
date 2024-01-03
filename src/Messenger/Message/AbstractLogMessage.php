<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Messenger\Message;

use Monolog\LogRecord;

abstract class AbstractLogMessage
{
    public function __construct(
        private readonly LogRecord $record,
    ) {
    }

    public function getRecord(): LogRecord
    {
        return $this->record;
    }
}
