<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Controller;

use JsonException;

final class HealthCheckControllerTest extends AbstractControllerTest
{
    /**
     * @throws JsonException
     */
    public function testHealthCheck(): void
    {
        $response = $this->get(uri: '/health');
        self::assertResponseIsSuccessful();

        $responseKeys = array_keys($response);
        $moduleResultsKeys = array_keys($response['moduleResults']);

        self::assertContains('healthy', $responseKeys);
        self::assertContains('time', $responseKeys);
        self::assertContains('lead_time', $responseKeys);

        self::assertContains('opcache', $moduleResultsKeys);
        self::assertContains('forwardIp', $moduleResultsKeys);
        self::assertContains('redis', $moduleResultsKeys);
        self::assertContains('dataMount', $moduleResultsKeys);
        self::assertContains('mongo', $moduleResultsKeys);
    }
}
