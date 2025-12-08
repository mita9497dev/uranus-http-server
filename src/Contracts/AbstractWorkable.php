<?php
namespace Mita\UranusHttpServer\Contracts;

use ReflectionClass;

abstract class AbstractWorkable implements WorkableInterface
{
    protected array $options = [];

    protected bool $running = false;

    public function __destruct()
    {
        $this->destroy();
    }

    public function destroy(): void
    {
        $this->running = false;
    }

    abstract public function execute(): void;

    public function run(): void 
    {
        $this->running = true;
        while ($this->running) {
            $this->execute();
        }
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function getOption(string $key, $default = null)
    {
        return $this->options[$key] ?? $default;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public static function getName(): string
    {
        return (new ReflectionClass(static::class))->getShortName();
    }
}
