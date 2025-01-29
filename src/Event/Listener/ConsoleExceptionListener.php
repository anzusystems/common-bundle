<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Event\Listener;

use AnzuSystems\CommonBundle\Log\Factory\LogContextFactory;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Event\ConsoleErrorEvent;

final readonly class ConsoleExceptionListener
{
    /**
     *
     */
    public function __construct(
        private readonly LoggerInterface $appLogger,
        private readonly array $ignoredExceptions = [],
        private readonly ?LogContextFactory $logContextFactory = null,
    ) {
    }

    /**
     * @throws SerializerException
     */
    public function __invoke(ConsoleErrorEvent $event): void
    {
        $exception = $event->getError();
        foreach ($this->ignoredExceptions as $ignoredException) {
            if (is_a($exception::class, $ignoredException, true)) {
                return;
            }
        }

        $context = [];
        if ($this->logContextFactory instanceof LogContextFactory) {
            $context = $this->logContextFactory->buildFromConsoleErrorEventToArray($event);
        }

        $this->appLogger->critical(sprintf(
            '[Command] [%s] %s',
            (string) $event->getCommand()?->getName(),
            $exception->getMessage()
        ), $context);
    }
}
