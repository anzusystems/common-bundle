<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Controller;

use AnzuSystems\Contracts\Entity\AnzuUser;
use Symfony\Component\HttpFoundation\Response;

final class DebugControllerTest extends AbstractControllerTest
{
    public function testLeadTime(): void
    {
        $result = $this->get(uri: '/debug/lead-time');
        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('lead_time', $result);
    }

    public function testOpCacheStatus(): void
    {
        $this->loginUser([AnzuUser::ROLE_ADMIN]);
        $result = $this->get(uri: '/debug/opcache');
        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('opcache', $result);
    }

    public function testIpCheck(): void
    {
        $this->loginUser([AnzuUser::ROLE_ADMIN]);
        $result = $this->get(uri: '/debug/ip');
        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('App', $result);
        self::assertArrayHasKey('Request', $result);
        self::assertArrayHasKey('SERVER', $result);
    }

    public function testError(): void
    {
        $this->loginUser([AnzuUser::ROLE_ADMIN]);
        $result = $this->get(uri: '/debug/error');
        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        self::assertSame('test', $result['detail']);
    }
}
