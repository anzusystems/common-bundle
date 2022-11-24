<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests;

use AnzuSystems\CommonBundle\Tests\Traits\AnzuKernelTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AnzuKernelTestCase extends KernelTestCase
{
    use AnzuKernelTrait;

    public static function setUpBeforeClass(): void
    {
        static::bootKernel();
    }
}
