<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Messenger\Stamp;

use AnzuSystems\Contracts\AnzuApp;
use Symfony\Component\Messenger\Stamp\StampInterface;

final class ContextIdentityStamp implements StampInterface
{
    public function __construct(
        private readonly string $contextId,
    ) {
    }

    public static function create(): self
    {
        return new self(AnzuApp::getContextId());
    }

    public function getContextId(): string
    {
        return $this->contextId;
    }
}
