<?php

namespace Mita\UranusHttpServer\Repositories;

use DI\ContainerBuilder;
use Mita\UranusHttpServer\Cache\EloquentCache;

class RepositoryProvider
{
    private array $repositories = [];

    public function addRepository(string $repository): void
    {
        $this->repositories[] = $repository;
    }

    public function register(ContainerBuilder $containerBuilder): void
    {
        foreach ($this->repositories as $repository) {
            $containerBuilder->addDefinitions([
                $repository => function($container) use ($repository) {
                    $eloquentCache = $container->get(EloquentCache::class);
                    $instance = new $repository;
                    $modelClass = $instance->getModel();
                    $model = new $modelClass();
                    return new $repository($model, $eloquentCache);
                }
            ]);
        }
    }
}