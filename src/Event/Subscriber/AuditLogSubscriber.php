<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Event\Subscriber;

use AnzuSystems\CommonBundle\Log\Factory\LogContextFactory;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class AuditLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $auditLogger,
        private LogContextFactory $logContextFactory,
        private array $loggedMethods,
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
        if (false === in_array($event->getRequest()->getMethod(), $this->loggedMethods, true)) {
            return;
        }
        if ($event->getRequest()->attributes->get(AuditLogResourceHelper::RESOURCE_EXCLUDE_ATTR_NAME)) {
            return;
        }

        $this->auditLogger->info(
            message: (string) $event->getRequest()->attributes->get('_route'),
            context: $this->logContextFactory->buildFromRequestToArray(
                $event->getRequest(),
                $event->getResponse()
            ),
        );
    }
}
