<?php
namespace Mita\UranusHttpServer\Contracts;

interface WorkableInterface
{
    public function execute(): void;
    public function destroy(): void;
    public function run(): void;
    public static function getName(): string;
    public function setOptions(array $options): void;
    public function getOption(string $key, $default = null);
    public function getOptions(): array;
}