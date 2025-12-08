<?php
namespace Mita\UranusHttpServer\Contracts;

use React\EventLoop\LoopInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;

abstract class AbstractLoopTask 
{
    protected LoopInterface $loop;
    protected LoggerInterface $logger;
    protected bool $isShuttingDown = false;
    protected array $config = [];
    protected int $startTime = 0;
    
    public function __construct(LoggerInterface $logger, array $config = [])
    {
        $this->logger = $logger;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->loop = Loop::get();
        $this->startTime = time();

        $this->registerSignalHandlers();
    }

    abstract protected function getDefaultConfig(): array;
    
    abstract protected function initialize(): void;
    
    abstract protected function processLoop(): void;
    
    abstract protected function cleanup(): void;

    protected function registerSignalHandlers(): void
    {
        pcntl_async_signals(true);
        
        pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
        pcntl_signal(SIGINT, [$this, 'handleShutdown']);
    }

    public function handleShutdown(): void
    {
        $this->isShuttingDown = true;
        $this->logger->info('Received shutdown signal');
        
        // Thêm timer để cleanup và stop loop
        $this->loop->addTimer(0.1, function() {
            $this->cleanup();
            $this->loop->stop();
        });
    }

    public function run(): void
    {
        try {
            $this->initialize();
            
            // Add periodic timer for main process
            $this->loop->addPeriodicTimer(0.1, function() {
                if ($this->isShuttingDown) {
                    return;
                }
                $this->processLoop();
            });

            // Add periodic timer for monitoring
            $this->loop->addPeriodicTimer(5, function() {
                $this->reportMetrics();
            });

            $this->logger->info('Task started');
            $this->loop->run();
            
        } catch (\Throwable $e) {
            $this->logger->error('Task error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    protected function reportMetrics(): void
    {
        // Implement basic metrics reporting
        $metrics = [
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'uptime' => time() - $this->startTime
        ];
        
        $this->logger->info('Task metrics', $metrics);
    }
}