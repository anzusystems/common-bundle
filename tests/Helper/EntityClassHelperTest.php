<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Helper;

use AnzuSystems\CommonBundle\Helper\EntityClassHelper;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\DeletableExample;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\DeletableExampleProxy;
use PHPUnit\Framework\TestCase;

final class EntityClassHelperTest extends TestCase
{
    public function testProxyResolvesToItsEntityClass(): void
    {
        self::assertSame(DeletableExample::class, EntityClassHelper::getRealClass(new DeletableExampleProxy()));
    }

    public function testEntityKeepsItsClass(): void
    {
        self::assertSame(DeletableExample::class, EntityClassHelper::getRealClass(new DeletableExample()));
    }
}
