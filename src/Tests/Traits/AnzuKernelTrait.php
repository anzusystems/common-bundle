<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Traits;

use AnzuSystems\CommonBundle\Kernel\AnzuKernel;

trait AnzuKernelTrait
{
    protected static function createKernel(array $options = []): AnzuKernel
    {
        /** @var class-string<AnzuKernel> $kernelClass */
        $kernelClass = static::getKernelClass();

        return new $kernelClass(
            appNamespace: (string) self::resolveKernelOption(options: $options, optionName: 'namespace', envName: 'APP_NAMESPACE', default: ''),
            appSystem: (string) self::resolveKernelOption(options: $options, optionName: 'name', envName: 'APP_SYSTEM', default: ''),
            appVersion: (string) self::resolveKernelOption(options: $options, optionName: 'version', envName: 'APP_VERSION', default: '0.0.0'),
            appReadOnlyMode: (bool) self::resolveKernelOption(options: $options, optionName: 'debug', envName: 'APP_READ_ONLY_MODE', default: false),
            environment: (string) self::resolveKernelOption(options: $options, optionName: 'environment', envName: 'APP_ENV', default: 'test'),
            debug: (bool) self::resolveKernelOption(options: $options, optionName: 'debug', envName: 'APP_DEBUG', default: true),
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
