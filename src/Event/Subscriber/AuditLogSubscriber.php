<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Event\Subscriber;

use AnzuSystems\CommonBundle\Log\Factory\LogContextFactory;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class AuditLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $auditLogger,
        private readonly LogContextFactory $logContextFactory,
        private readonly array $loggedMethods,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'onTerminate',
        ];
    }

    /**
     * @throws SerializerException
     */
    public function onTerminate(TerminateEvent $event): void
    {
        if (in_array($event->getRequest()->getMethod(), $this->loggedMethods, true)) {
            $this->auditLogger->info(
                (string) $event->getRequest()->attributes->get('_route'),
                $this->logContextFactory->buildFromRequestToArray(
                    $event->getRequest(),
                    $event->getResponse()
                )
            );
        }
    }
}
