<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Monolog;

use Monolog\Level;
use Monolog\LogRecord;

final readonly class IgnoreExceptionProcessor
{
    public function __construct(
        private array $ignoredExceptions = []
    ) {
    }

    public function __invoke(LogRecord $record): ?LogRecord
    {
        if (isset($record->context['exception'])) {
            $exception = $record->context['exception'];

            foreach ($this->ignoredExceptions as $ignoredClass) {
                if ($exception instanceof $ignoredClass) {
                    // downgrade ignored exception to DEBUG level so important handlers will ignore it
                    return $record->with(
                        level: Level::Debug,
                    );
                }
            }
        }

        return $record;
    }
}
