<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Messenger\Message;

abstract class AbstractLogMessage
{
    public function __construct(
        private readonly array $record,
    ) {
    }

    public function getRecord(): array
    {
        return $this->record;
    }
}
