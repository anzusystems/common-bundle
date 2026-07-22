<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Traits;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\Attribute\Required;

trait JournalLoggerAwareTrait
{
    protected ?LoggerInterface $journalLogger = null;

    #[Required]
    public function setJournalLogger(
        LoggerInterface $journalLogger
    ): void {
        $this->journalLogger = $journalLogger;
    }
}
