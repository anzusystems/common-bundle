<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Messenger\MonologHandler;

use AnzuSystems\CommonBundle\Messenger\Message\AbstractLogMessage;
use Monolog\Handler\AbstractHandler;
use Monolog\LogRecord;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessengerHandler extends AbstractHandler
{
    public function __construct(
        private readonly string $messageClass,
        private readonly MessageBusInterface $messageBus,
        mixed $level = 100, // not using Monolog\Logger::DEBUG because deprecated, not using Monolog\Level::Debug because backwards compatibility.
        bool $bubble = true,
    ) {
        if (false === is_subclass_of($messageClass, AbstractLogMessage::class)) {
            throw new InvalidArgumentException(sprintf(
                'Expected instance of "%s", instance of "%s" given',
                AbstractLogMessage::class,
                $this->messageClass
            ));
        }

        parent::__construct($level, $bubble);
    }

    public function handle(LogRecord|array $record): bool
    {
        /** @var AbstractLogMessage $class */
        $class = $this->messageClass;
        $this->messageBus->dispatch(
            new $class($record),
        );

        return false === $this->bubble;
    }
}
