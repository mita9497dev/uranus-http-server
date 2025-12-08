<?php
namespace Mita\UranusHttpServer\Jobs;

use React\Promise\PromiseInterface;
use React\Promise\Promise;

abstract class AbstractAsyncJob extends AbstractJob implements JobInterface
{
    protected bool $isAsync = false;
    protected int $maxConcurrency = 1;
    protected int $timeout = 30;
    protected bool $enableRetry = false;
    protected int $maxRetries = 3;
    
    abstract public function execute(array $data = []): void;
    
    public function executeAsync(array $data = []): PromiseInterface
    {
        return new Promise(function($resolve, $reject) use ($data) {
            try {
                $this->execute($data);
                $resolve('success');
            } catch (\Throwable $e) {
                $reject($e);
            }
        });
    }
    
    // Getter methods for async configuration
    public function isAsync(): bool
    {
        return $this->isAsync;
    }
    
    public function getMaxConcurrency(): int
    {
        return $this->maxConcurrency;
    }
    
    public function getTimeout(): int
    {
        return $this->timeout;
    }
    
    public function shouldRetry(): bool
    {
        return $this->enableRetry;
    }
    
    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }
} 