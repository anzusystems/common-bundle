<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests;

use AnzuSystems\Contracts\AnzuApp;
use DateTimeImmutable;

final class AnzuAppTest extends AnzuKernelTestCase
{
    public function testTes(): void
    {
        self::assertSame('0.0.0', AnzuApp::getAppVersion());
        self::assertNotEmpty(AnzuApp::getContextId());
        self::assertEquals('test-0.0.0', AnzuApp::getAppVersionWithSystem());
        self::assertFalse(AnzuApp::isReadOnlyMode());
        self::assertSame(
            (new DateTimeImmutable(AnzuApp::DATETIME_MAX))->getTimestamp(),
            AnzuApp::getMaxDate()->getTimestamp()
        );
        self::assertSame(
            (new DateTimeImmutable(AnzuApp::DATETIME_MIN))->getTimestamp(),
            AnzuApp::getMinDate()->getTimestamp()
        );
        self::assertNotEmpty(AnzuApp::getAppDate());
        self::assertSame('test', AnzuApp::getAppEnv());
        self::assertNotEmpty(AnzuApp::getProjectDir());
        self::assertNotEmpty(AnzuApp::getDataDir());
        self::assertNotEmpty(AnzuApp::getDownloadDir());
        self::assertNotEmpty(AnzuApp::getPidDir());
    }
}
