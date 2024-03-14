<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Traits;

use AnzuSystems\CommonBundle\Kernel\AnzuKernel;

trait AnzuKernelTrait
{
    /**
     * @psalm-suppress UnsafeInstantiation
     */
    protected static function createKernel(array $options = []): AnzuKernel
    {
        /** @var class-string<AnzuKernel> $kernelClass */
        $kernelClass = static::getKernelClass();

        return new $kernelClass(
            appNamespace: (string) self::resolveKernelOption($options, 'namespace', 'APP_NAMESPACE', ''),
            appSystem: (string) self::resolveKernelOption($options, 'name', 'APP_SYSTEM', ''),
            appVersion: (string) self::resolveKernelOption($options, 'version', 'APP_VERSION', '0.0.0'),
            appReadOnlyMode: (bool) self::resolveKernelOption($options, 'debug', 'APP_READ_ONLY_MODE', false),
            environment: (string) self::resolveKernelOption($options, 'environment', 'APP_ENV', 'test'),
            debug: (bool) self::resolveKernelOption($options, 'debug', 'APP_DEBUG', true),
        );
    }

    private static function resolveKernelOption(
        array $options,
        string $optionName,
        string $envName,
        mixed $default
    ): mixed {
        return $options[$optionName] ?? $_ENV[$envName] ?? $_SERVER[$envName] ?? $default;
    }
}
