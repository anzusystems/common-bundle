<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Messenger\Message;

use Monolog\LogRecord;

abstract class AbstractLogMessage
{
    public function __construct(
        private readonly array|LogRecord $record,
    ) {
    }

    public function getRecord(): array|LogRecord
    {
        return $this->record;
    }
}
