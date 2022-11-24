<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Log\Factory;

use AnzuSystems\CommonBundle\Document\Log;
use AnzuSystems\CommonBundle\Document\LogContext;
use AnzuSystems\CommonBundle\Log\Model\LogDto;

final class LogFactory
{
    public static function buildCustomLog(LogDto $logDto, LogContext $context): Log
    {
        return (new Log())
            ->setLevelName($logDto->getLevel()->toString())
            ->setMessage($logDto->getMessage())
            ->setContext($context)
        ;
    }
}
