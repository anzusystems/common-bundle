<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model;

use Doctrine\Persistence\Proxy;

/**
 * Stands in for the proxy class Doctrine generates without native lazy objects.
 *
 * @implements Proxy<DeletableExample>
 */
final class DeletableExampleProxy extends DeletableExample implements Proxy
{
    public function __load(): void
    {
    }

    public function __isInitialized(): bool
    {
        return true;
    }
}
