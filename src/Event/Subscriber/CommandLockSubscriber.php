<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Event\Subscriber;

use AnzuSystems\Contracts\AnzuApp;
use Redis;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\Store\RedisStore;

final class CommandLockSubscriber implements EventSubscriberInterface
{
    public const REDIS_LOCK_PREFIX = 'cmd_lock_';

    private ?LockInterface $lock = null;
    private string $lockName;

    public function __construct(
        private readonly Redis $appRedis,
        private readonly array $unlockedCommands,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'acquireLock',
            ConsoleEvents::TERMINATE => 'releaseLock',
            ConsoleEvents::ERROR => 'releaseLock',
        ];
    }

    public function acquireLock(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        if ($command instanceof LazyCommand) {
            $command = $command->getCommand();
        }

        if ($command instanceof Command && false === in_array($command::class, $this->unlockedCommands, true)) {
            $this->setLockName($command, $event->getInput());
            $this->lock = $this->createLock();
            if (false === $this->lock->acquire()) {
                $event->disableCommand();

                return;
            }
            file_put_contents($this->getPidFilename(), (string) getmypid(), LOCK_EX);
        }
    }

    public function releaseLock(ConsoleTerminateEvent | ConsoleErrorEvent $event): void
    {
        if ($this->lock && $this->lock->isAcquired()) {
            $this->lock->release();
            unlink($this->getPidFilename());
        }
    }

    private function createLock(): LockInterface
    {
        $redisStore = new RedisStore($this->appRedis);

        return (new LockFactory($redisStore))->createLock(self::REDIS_LOCK_PREFIX . $this->lockName);
    }

    private function getPidFilename(): string
    {
        return AnzuApp::getPidDir() . '/' . $this->lockName . '.pid';
    }

    private function setLockName(Command $command, InputInterface $input): void
    {
        if (empty($command->getName())) {
            $this->lockName = (string) uuid_create();

            return;
        }

        $this->lockName = (string) preg_replace(
            '/[^a-zA-z_]/',
            '_',
            ltrim($this->getNameFromArgs($input->getArguments()), '_')
        );
    }

    private function getNameFromArgs(array $args): string
    {
        $name = '';
        foreach ($args as $arg) {
            if (is_array($arg)) {
                $name .= $this->getNameFromArgs($arg);

                continue;
            }
            $name .= '__' . $arg;
        }

        return $name;
    }
}
