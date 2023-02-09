<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests;

use AnzuSystems\CommonBundle\Tests\Traits\AnzuKernelTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AnzuWebTestCase extends WebTestCase
{
    use AnzuKernelTrait;

    protected static KernelBrowser $client;

    protected function setUp(): void
    {
        static::$client = static::createClient();
        static::$client->disableReboot();
    }

    protected function assertValidationErrors(array $responseContent, array $expectedValidationErrors): void
    {
        $this->assertArrayHasKey('error', $responseContent);
        $this->assertArrayHasKey('fields', $responseContent);
        $this->assertSame('validation_failed', $responseContent['error']);
        $this->assertSameSize($expectedValidationErrors, $responseContent['fields']);
        foreach ($expectedValidationErrors as $fieldName => $errors) {
            foreach ($errors as $error) {
                $this->assertContains($error, $responseContent['fields'][$fieldName]);
            }
        }
    }
}
