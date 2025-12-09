<?php

namespace Mita\UranusHttpServer\Helpers;

use Psr\Container\ContainerInterface;
use RuntimeException;

class UranusHelper
{
    protected static ?ContainerInterface $container = null;

    /**
     * Set the container instance.
     *
     * @param ContainerInterface $container
     * @return void
     */
    public static function setContainer(ContainerInterface $container): void
    {
        static::$container = $container;
    }

    /**
     * Get the container instance.
     *
     * @return ContainerInterface
     * @throws RuntimeException
     */
    public static function getContainer(): ContainerInterface
    {
        if (static::$container === null) {
            throw new RuntimeException('Container has not been set');
        }

        return static::$container;
    }
}
