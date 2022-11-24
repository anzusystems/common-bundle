<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Messenger\Middleware;

use AnzuSystems\CommonBundle\Messenger\Stamp\ContextIdentityStamp;
use AnzuSystems\Contracts\AnzuApp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Adds context identity stamp to each message and during handling it sets context id from this stamp.
 */
final class ContextIdentityMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        /** @var ContextIdentityStamp|null $contextIdentityStamp */
        $contextIdentityStamp = $envelope->last(ContextIdentityStamp::class);
        if (null === $contextIdentityStamp) {
            $envelope = $envelope->with(ContextIdentityStamp::create());

            return $stack->next()->handle($envelope, $stack);
        }

        AnzuApp::setContextId($contextIdentityStamp->getContextId());

        return $stack->next()->handle($envelope, $stack);
    }
}
