<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\HealthCheck\Module;

use AnzuSystems\Contracts\AnzuApp;

final class DataMountModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'dataMount';
    }

    public function isHealthy(): bool
    {
        $filename = AnzuApp::getDataDir() . '/' . AnzuApp::getContextId();
        $healthy = (bool) file_put_contents($filename, AnzuApp::getContextId());
        if ($healthy) {
            $healthy = unlink($filename);
        }

        return $healthy;
    }
}
