<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Controller;

use AnzuSystems\CommonBundle\Kernel\AnzuKernel;
use AnzuSystems\Contracts\AnzuApp;
use JsonException;

final class ContentIdOnResponseTest extends AbstractControllerTest
{
    /**
     * @throws JsonException
     */
    public function testContextId(): void
    {
        $this->get(uri: '/something');

        $this->assertTrue(self::$client->getResponse()->headers->has(AnzuKernel::CONTEXT_IDENTITY_HEADER));
        $this->assertSame(
            expected: AnzuApp::getContextId(),
            actual: self::$client->getResponse()->headers->get(AnzuKernel::CONTEXT_IDENTITY_HEADER),
        );
    }
}
