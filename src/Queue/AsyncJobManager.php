<?php
namespace Mita\UranusHttpServer\Queue;

use Mita\UranusHttpServer\Jobs\AbstractAsyncJob;
use Mita\UranusHttpServer\Jobs\JobInterface;
use Mita\UranusHttpServer\Contracts\WorkableRegistry;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use Psr\Container\ContainerInterface;

class AsyncJobManager extends WorkerManager
{
    protected LoopInterface $loop;
    protected array $jobStats = [];
    protected array $activePromises = [];
    protected array $timeoutTimers = [];
    protected ContainerInterface $container;
    
    public function __construct(
        QueueInterface $queue,
        LoggerInterface $logger,
        WorkableRegistry $registry,
        LoopInterface $loop,
        ContainerInterface $container
    ) {
        parent::__construct($queue, $logger, $registry);
        $this->loop = $loop;
        $this->container = $container;
    }
    
    public function startAsyncProcessing(string $jobName, AbstractAsyncJob $jobTemplate): void
    {
        $maxConcurrency = $jobTemplate->getMaxConcurrency();
        $timeout = $jobTemplate->getTimeout();
        
        // Initialize job stats
        $this->jobStats[$jobName] = [
            'running' => 0,
            'completed' => 0,
            'failed' => 0,
            'max_concurrency' => $maxConcurrency,
            'timeout' => $timeout,
            'retry_enabled' => $jobTemplate->shouldRetry(),
            'max_retries' => $jobTemplate->getMaxRetries()
        ];
        
        $this->logger->info("Starting async job processing", [
            'job_name' => $jobName,
            'max_concurrency' => $maxConcurrency,
            'timeout' => $timeout,
            'retry_enabled' => $jobTemplate->shouldRetry()
        ]);
        
        // Start initial concurrent jobs up to max
        for ($i = 0; $i < $maxConcurrency; $i++) {
            $this->processNextJob($jobName, $jobTemplate);
        }
        
        // Periodic check for new jobs
        $this->loop->addPeriodicTimer(1, function() use ($jobName, $jobTemplate) {
            $this->processNextJob($jobName, $jobTemplate);
        });
        
        // Stats reporting timer
        $this->loop->addPeriodicTimer(30, function() use ($jobName) {
            $this->reportJobStats($jobName);
        });
    }
    
    protected function processNextJob(string $jobName, AbstractAsyncJob $jobTemplate): void
    {
        $stats = $this->jobStats[$jobName];
        
        if ($stats['running'] >= $stats['max_concurrency']) {
            return; // Đã đạt max concurrency
        }
        
        $data = $this->getNextJob($jobName);
        if (!$data) {
            return; // Không có job nào
        }
        
        // Tạo job instance mới cho mỗi execution
        $jobClass = $this->registry->get($jobName);
        $jobInstance = $this->container->get($jobClass);
        
        // Inject event loop if job supports it
        if (method_exists($jobInstance, 'setLoop')) {
            $jobInstance->setLoop($this->loop);
        }
        
        $jobId = uniqid('async_job_', true);
        
        $this->jobStats[$jobName]['running']++;
        
        $this->logger->info("Starting async job execution", [
            'job_name' => $jobName,
            'job_id' => $jobId,
            'running_jobs' => $this->jobStats[$jobName]['running'],
            'data' => $data
        ]);
        
        $promise = $jobInstance->executeAsync($data);
        $this->activePromises[$jobId] = $promise;
        
        // Set timeout theo job config
        $timeoutTimer = $this->loop->addTimer($stats['timeout'], function() use ($jobId, $jobName) {
            $this->handleJobTimeout($jobName, $jobId);
        });
        $this->timeoutTimers[$jobId] = $timeoutTimer;
        
        $promise->then(
            function($result) use ($jobName, $jobId, $jobTemplate) {
                $this->onJobSuccess($jobName, $jobId, $result);
                
                // Try to process next job
                $this->processNextJob($jobName, $jobTemplate);
            },
            function(\Throwable $error) use ($jobName, $jobId, $jobTemplate, $data) {
                $this->onJobError($jobName, $jobId, $error, $jobTemplate, $data);
                
                // Try to process next job
                $this->processNextJob($jobName, $jobTemplate);
            }
        );
    }
    
    protected function onJobSuccess(string $jobName, string $jobId, $result): void
    {
        // Clean up
        $this->cleanupJob($jobId);
        
        $this->jobStats[$jobName]['running']--;
        $this->jobStats[$jobName]['completed']++;
        
        $this->logger->info("Async job completed successfully", [
            'job_name' => $jobName,
            'job_id' => $jobId,
            'result' => $result,
            'stats' => $this->jobStats[$jobName]
        ]);
    }
    
    protected function onJobError(string $jobName, string $jobId, \Throwable $error, AbstractAsyncJob $jobTemplate, array $data): void
    {
        // Clean up
        $this->cleanupJob($jobId);
        
        $this->jobStats[$jobName]['running']--;
        $this->jobStats[$jobName]['failed']++;
        
        $this->logger->error("Async job failed", [
            'job_name' => $jobName,
            'job_id' => $jobId,
            'error' => $error->getMessage(),
            'stats' => $this->jobStats[$jobName]
        ]);
        
        // Handle retry theo job config
        if ($jobTemplate->shouldRetry()) {
            $this->handleJobRetry($jobName, $data, $error, $jobTemplate);
        }
    }
    
    protected function handleJobTimeout(string $jobName, string $jobId): void
    {
        $this->logger->warning("Job timeout", [
            'job_name' => $jobName,
            'job_id' => $jobId,
            'timeout' => $this->jobStats[$jobName]['timeout']
        ]);
        
        // Clean up
        $this->cleanupJob($jobId);
        
        $this->jobStats[$jobName]['running']--;
        $this->jobStats[$jobName]['failed']++;
    }
    
    protected function handleJobRetry(string $jobName, array $data, \Throwable $error, AbstractAsyncJob $jobTemplate): void
    {
        $maxRetries = $jobTemplate->getMaxRetries();
        $currentRetries = $data['_retry_count'] ?? 0;
        
        if ($currentRetries < $maxRetries) {
            $data['_retry_count'] = $currentRetries + 1;
            $data['_last_error'] = $error->getMessage();
            
            $this->logger->info("Retrying failed job", [
                'job_name' => $jobName,
                'retry_count' => $data['_retry_count'],
                'max_retries' => $maxRetries,
                'error' => $error->getMessage()
            ]);
            
            // Add back to queue with retry info
            $this->queue->push($jobTemplate::getQueue(), $data);
        } else {
            $this->logger->error("Job failed after max retries", [
                'job_name' => $jobName,
                'retry_count' => $currentRetries,
                'max_retries' => $maxRetries,
                'final_error' => $error->getMessage()
            ]);
        }
    }
    
    protected function cleanupJob(string $jobId): void
    {
        // Cancel timeout timer
        if (isset($this->timeoutTimers[$jobId])) {
            $this->loop->cancelTimer($this->timeoutTimers[$jobId]);
            unset($this->timeoutTimers[$jobId]);
        }
        
        // Remove from active promises
        unset($this->activePromises[$jobId]);
    }
    
    protected function reportJobStats(string $jobName): void
    {
        $stats = $this->jobStats[$jobName];
        
        $utilization = $stats['max_concurrency'] > 0 
            ? round(($stats['running'] / $stats['max_concurrency']) * 100, 2) 
            : 0;
        
        $this->logger->info("Job statistics report", [
            'job_name' => $jobName,
            'running' => $stats['running'],
            'completed' => $stats['completed'],
            'failed' => $stats['failed'],
            'utilization' => $utilization . '%',
            'active_promises' => count($this->activePromises)
        ]);
    }
    
    public function getJobStats(string $jobName): ?array
    {
        return $this->jobStats[$jobName] ?? null;
    }
    
    public function getAllJobStats(): array
    {
        return $this->jobStats;
    }
    
    public function stopProcessing(string $jobName): void
    {
        $this->logger->info("Stopping async job processing", [
            'job_name' => $jobName
        ]);
        
        // Cancel all active promises for this job
        foreach ($this->activePromises as $jobId => $promise) {
            $this->cleanupJob($jobId);
        }
        
        // Remove job stats
        unset($this->jobStats[$jobName]);
    }
} 