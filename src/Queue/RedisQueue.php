<?php
namespace Mita\UranusHttpServer\Queue;

use Psr\Log\LoggerInterface;
use Redis;

class RedisQueue implements QueueInterface
{
    private Redis $redis;
    private array $config;
    private LoggerInterface $logger;

    public function __construct(array $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->initializeConnection();
    }

    private function initializeConnection(): void
    {
        $this->redis = new Redis();
        $this->redis->connect(
            $this->config['host'],
            $this->config['port'],
            $this->config['timeout'] ?? 0.0,
            null,
            $this->config['retry_interval'] ?? 0
        );

        if (!empty($this->config['password'])) {
            $this->redis->auth($this->config['password']);
        }

        if (isset($this->config['database'])) {
            $this->redis->select((int) $this->config['database']);
        }
    }

    /** @override */
    public function getQueuePrefix(): string
    {
        return $this->config['prefix'] ?? '';
    }

    public function push(string $queue, array $job): void
    {
        try {
            $this->redis->lPush($queue, json_encode($job));
            $this->logger->info("Job pushed to queue: {$queue}");
        } catch (\Exception $e) {
            $this->logger->error("Failed to push job to queue: {$queue}", ['exception' => $e]);
        }
    }

    public function pop(string $queue): ?array
    {
        try {
            $message = $this->redis->rPop($queue);
            if (!$message) {
                return null;
            }
            $this->logger->info("Job popped from queue: {$queue}");
            return json_decode($message, true);
        } catch (\Exception $e) {
            $this->logger->error("Failed to pop job from queue: {$queue}", ['exception' => $e]);
            return null;
        }
    }

    public function size(string $queue): int
    {
        try {
            return $this->redis->lLen($queue);
        } catch (\Exception $e) {
            $this->logger->error("Failed to get queue size: {$queue}", ['exception' => $e]);
            return 0;
        }
    }

    public function clear(string $queue): void
    {
        try {
            $this->redis->del($queue);
            $this->logger->info("Queue cleared: {$queue}");
        } catch (\Exception $e) {
            $this->logger->error("Failed to clear queue: {$queue}", ['exception' => $e]);
        }
    }
}
