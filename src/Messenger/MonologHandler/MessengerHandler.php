<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Messenger\MonologHandler;

use AnzuSystems\CommonBundle\Messenger\Message\AbstractLogMessage;
use Monolog\Handler\AbstractHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessengerHandler extends AbstractHandler
{
    public function __construct(
        private readonly string $messageClass,
        private readonly MessageBusInterface $messageBus,
        Level $level = Level::Debug,
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

    public function handle(LogRecord $record): bool
    {
        /** @var AbstractLogMessage $class */
        $class = $this->messageClass;
        $this->messageBus->dispatch(
            new $class($record),
        );

        return false === $this->bubble;
    }
}
