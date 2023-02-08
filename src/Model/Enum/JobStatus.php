<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\Enum;

use AnzuSystems\Contracts\Model\Enum\BaseEnumTrait;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;

enum JobStatus: string implements EnumInterface
{
    use BaseEnumTrait;

    case Waiting = 'waiting';
    case WaitingBatch = 'waiting_batch';
    case Processing = 'processing';
    case Done = 'done';
    case Error = 'error';

    public const Default = self::Waiting;

    public const PROCESSABLE_STATUSES = [
        self::Waiting,
        self::WaitingBatch,
    ];
}
