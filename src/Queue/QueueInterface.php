<?php
namespace Mita\UranusHttpServer\Queue;

use Mita\UranusHttpServer\Jobs\JobInterface;

interface QueueInterface
{
    public function push(string $queue, array $job): void;
    public function pop(string $queue): ?array;
    public function size(string $queue): int;
    public function clear(string $queue): void;
    public function getQueuePrefix(): string;
}
