<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Messenger\MonologHandler;

use AnzuSystems\CommonBundle\Messenger\Message\AbstractLogMessage;
use Monolog\Handler\AbstractHandler;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessengerHandler extends AbstractHandler
{
    public function __construct(
        private readonly string $messageClass,
        private readonly MessageBusInterface $messageBus,
        mixed $level = Logger::DEBUG,
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

    public function handle(array $record): bool
    {
        /** @var class-string $class */
        $class = $this->messageClass;
        /** @psalm-suppress UnsafeInstantiation */
        $this->messageBus->dispatch(
            new $class($record),
        );

        return false === $this->bubble;
    }
}
