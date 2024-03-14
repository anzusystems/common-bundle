<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Router;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Routing\RouteCollection;

final class AnzuDomainRouteLoader extends Loader
{
    public const TYPE = 'anzu_domain';

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $routes = new RouteCollection();
        $finder = Finder::create();
        $finder
            ->in($resource)
            ->name('Controller')
            ->directories()
        ;
        foreach ($finder as $dir) {
            $finderController = Finder::create();
            $finderController
                ->in($dir->getPathname())
                ->name('*Controller.php')
            ;
            foreach ($finderController as $controller) {
                $importedRoutes = $this->import($controller->getPathname(), 'attribute');
                if ($importedRoutes instanceof RouteCollection) {
                    $importedRoutes->addPrefix('/' . strtolower($controller->getRelativePath()) . '/');
                }
                $routes->addCollection($importedRoutes);
            }
        }

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return self::TYPE === $type;
    }
}
