<?php
namespace Mita\UranusHttpServer\Contracts;

use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Process\Process;

abstract class AbstractTaskManager
{
    protected LoggerInterface $logger;
    protected CacheInterface $cache;
    protected array $config;
    protected string $cachePrefix = 'task_manager:';

    public function __construct(
        CacheInterface $cache,
        LoggerInterface $logger,
        array $config = []
    ) {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->cache = $cache;
        $this->setLogger($logger);
    }

    abstract protected function getDefaultConfig(): array;
    
    abstract protected function getCommand(string $processId, array $options = []): array;

    abstract protected function getCacheName(): string;

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function start(string $processId, array $options = []): bool 
    {
        $taskInfo = $this->getTaskInfo($processId);
        if ($taskInfo && $this->isProcessRunning($taskInfo['pid'])) {
            $this->logger->warning("Task {$processId} already running");
            return false;
        }

        try {
            $command = $this->getCommand($processId, $options);
            $process = new Process($command);
            $process->enableOutput();
            $process->setTimeout(null);
            
            $process->start(function ($type, $buffer) use ($processId) {
                if (Process::ERR === $type) {
                    $this->logger->error($buffer, ['process_id' => $processId]);
                } else {
                    $this->logger->info($buffer, ['process_id' => $processId]);
                }
            });

            $taskInfo = [
                'pid' => $process->getPid(),
                'start_time' => time(),
                'status' => 'running',
                'options' => $options
            ];

            $this->saveTaskInfo($processId, $taskInfo);
            return true;

        } catch (\Throwable $e) {
            $this->logger->error("Failed to start task: " . $e->getMessage());
            return false;
        }
    }

    public function stop(string $processId): bool
    {
        $taskInfo = $this->getTaskInfo($processId);
        if (!$taskInfo) {
            $this->logger->warning("Task {$processId} not found");
            return false;
        }

        try {
            if ($this->isProcessRunning($taskInfo['pid'])) {
                if (PHP_OS_FAMILY === 'Linux') {
                    posix_kill($taskInfo['pid'], SIGTERM);
                } else {
                    // Windows
                    exec("taskkill /PID {$taskInfo['pid']} /F");
                }
                sleep(1); // Đợi process kết thúc
                
                if ($this->isProcessRunning($taskInfo['pid'])) {
                    if (PHP_OS_FAMILY === 'Linux') {
                        posix_kill($taskInfo['pid'], SIGTERM);
                    } else {
                        // Windows
                        exec("taskkill /PID {$taskInfo['pid']} /F");
                    }
                }
            }
            
            $this->removeTaskInfo($processId);
            $this->logger->info("Stopped task {$processId}");
            
            return true;
        } catch (\Throwable $e) {
            $this->logger->error("Failed to stop task: " . $e->getMessage());
            return false;
        }
    }

    public function status(string $processId): array
    {
        $taskInfo = $this->getTaskInfo($processId);
        if (!$taskInfo) {
            return ['status' => 'not_found'];
        }

        try {
            if (!$this->isProcessRunning($taskInfo['pid'])) {
                $this->removeTaskInfo($processId);
                return ['status' => 'stopped'];
            }

            $status = [
                'status' => 'running',
                'pid' => $taskInfo['pid'],
                'start_time' => $taskInfo['start_time'],
                'uptime' => time() - $taskInfo['start_time'],
                'uptime_formatted' => $this->formatUptime(time() - $taskInfo['start_time']),
                'memory' => $this->getMemoryUsage($taskInfo['pid']),
                'cpu' => $this->getCpuUsage($taskInfo['pid'])
            ];

            return array_merge($taskInfo['options'], $status);
        } catch (\Throwable $e) {
            $this->logger->error("Failed to get status: " . $e->getMessage());
            return ['status' => 'unknown'];
        }
    }

    public function list(): array
    {
        $result = [];
        $pattern = $this->getCacheKey();
        $keys = $this->cache->get($pattern) ?: [];
        
        foreach ($keys as $key => $info) {
            $processId = $this->getProcessIdFromCacheKey($key);
            $result[$processId] = $this->status($processId);
        }
        return $result;
    }

    public function stopAll(): array
    {
        $result = [];
        $pattern = $this->getCacheKey();
        $keys = $this->cache->get($pattern) ?: [];
        
        foreach ($keys as $key => $info) {
            $processId = $this->getProcessIdFromCacheKey($key);
            $result[$processId] = $this->stop($processId);
            
            if ($result[$processId]) {
                $this->logger->info("Stopped task {$processId}");
            } else {
                $this->logger->error("Failed to stop task {$processId}");
            }
        }

        return $result;
    }

    protected function getMemoryUsage(int $pid): int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return 0;
        } else {
            try {
                $cmd = "ps -o rss= -p {$pid}";
                return (int)trim(shell_exec($cmd));
            } catch (\Throwable $e) {
                return 0;
            }
        }
    }

    protected function getCpuUsage(int $pid): float
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return 0.0;
        } else {
            try {
                $cmd = "ps -o %cpu= -p {$pid}";
                return (float)trim(shell_exec($cmd));
            } catch (\Throwable $e) {
                return 0.0;
            }
        }
    }

    public function isProcessRunning(string $pid): bool
    {
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $cmd = "tasklist /FI \"PID eq $pid\" 2>NUL";
                $output = shell_exec($cmd);
                return strpos($output, $pid) !== false;
            } else {
                return posix_kill($pid, 0);
            }
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getCacheKey(): string 
    {
        return str_replace(
            [':', '@', '/', '\\', '{', '}', '(', ')'],
            ['_', '_', '_', '_', '_', '_', '_', '_'],
            $this->cachePrefix . $this->getCacheName()
        );
    }

    private function getProcessIdFromCacheKey(string $cacheKey): string
    {
        return str_replace($this->cachePrefix, '', $cacheKey);
    }

    private function getTaskInfo(string $processId): ?array
    {
        $cacheKey = $this->getCacheKey();
        $cacheInfo = $this->cache->get($cacheKey);
        if (is_array($cacheInfo) && isset($cacheInfo[$processId])) {
            return $cacheInfo[$processId];
        }
        return null;
    }

    private function saveTaskInfo(string $processId, array $info): void
    {
        $cacheInfo = $this->cache->get($this->getCacheKey());
        if (!is_array($cacheInfo)) {
            $cacheInfo = [];
        }
        $cacheInfo[$processId] = $info;
        $this->cache->set($this->getCacheKey(), $cacheInfo);
    }

    private function removeTaskInfo(string $processId): void
    {
        $cacheInfo = $this->cache->get($this->getCacheKey());
        if (is_array($cacheInfo)) {
            unset($cacheInfo[$processId]);
            $this->cache->set($this->getCacheKey(), $cacheInfo);
        }
    }

    private function formatUptime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        $parts = [];
        if ($days > 0) $parts[] = "{$days}d";
        if ($hours > 0) $parts[] = "{$hours}h";
        if ($minutes > 0) $parts[] = "{$minutes}m";
        if ($secs > 0) $parts[] = "{$secs}s";

        return implode(' ', $parts);
    }
}