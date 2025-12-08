<?php
namespace Mita\UranusHttpServer\Contracts;

use ReflectionClass;

class WorkableRegistry
{
    private static array $items = [];

    public static function register(string $class): void
    {
        if (!(new ReflectionClass($class))->implementsInterface(WorkableInterface::class)) {
            throw new \InvalidArgumentException("Class $class must implement WorkableInterface");
        }
        self::$items[$class::getName()] = $class;
    }

    public static function get(string $name): ?string
    {
        return self::$items[$name] ?? null;
    }

    public static function getAll(): array
    {
        return self::$items;
    }
}