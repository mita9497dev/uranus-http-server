<?php
namespace Mita\UranusHttpServer\Queue;

use Mita\UranusHttpServer\Jobs\JobInterface;
use Mita\UranusHttpServer\Contracts\WorkableRegistry;
use Psr\Log\LoggerInterface;

class WorkerManager
{
    protected QueueInterface $queue;
    protected LoggerInterface $logger;
    protected WorkableRegistry $registry;

    public function __construct(QueueInterface $queue, LoggerInterface $logger, WorkableRegistry $registry)
    {
        $this->queue = $queue;
        $this->logger = $logger;
        $this->registry = $registry;
    }

    public function getNextJob(string $jobName): ?array
    {
        $jobClass = $this->registry->get($jobName);
        if (!$jobClass) {
            $this->logger->warning("Job class not found: $jobName");
            return null;
        }

        $queueName = $jobClass::getQueue();
        $jobData = $this->queue->pop($queueName);
        
        if (is_string($jobData)) {
            return json_decode($jobData, true);
        }

        if (is_array($jobData)) {
            return $jobData;
        }
        
        return null;
    }

    public function pushJob(string $jobClass, array $data): void
    {
        $this->queue->push($jobClass::getQueue(), $data);
    }
}