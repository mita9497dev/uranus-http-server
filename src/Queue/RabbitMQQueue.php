<?php
namespace Mita\UranusHttpServer\Queue;

use Mita\UranusHttpServer\Jobs\JobInterface;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use Psr\Log\LoggerInterface;

class RabbitMQQueue implements QueueInterface
{
    private const QUEUE_PASSIVE = false;
    private const QUEUE_DURABLE = true;
    private const QUEUE_EXCLUSIVE = false;
    private const QUEUE_AUTO_DELETE = false;

    private AMQPStreamConnection $connection;
    private ?\PhpAmqpLib\Channel\AMQPChannel $channel = null;
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
        $this->connection = new AMQPStreamConnection(
            $this->config['host'],
            $this->config['port'],
            $this->config['user'],
            $this->config['password'],
            $this->config['vhost'] ?? '/'
        );
    }

    /** @override */
    public function getQueuePrefix(): string
    {
        return $this->config['prefix'] ?? '';
    }

    private function ensureConnection(): void
    {
        if (!$this->connection->isConnected()) {
            $this->initializeConnection();
        }
    }

    private function getChannel(): \PhpAmqpLib\Channel\AMQPChannel
    {
        $this->ensureConnection();
        if ($this->channel === null || !$this->channel->is_open()) {
            $this->channel = $this->connection->channel();
        }
        return $this->channel;
    }

    public function push(string $queue, array $job): void
    {
        try {
            $channel = $this->getChannel();
            $channel->queue_declare($queue, self::QUEUE_PASSIVE, self::QUEUE_DURABLE, self::QUEUE_EXCLUSIVE, self::QUEUE_AUTO_DELETE);
            $message = new AMQPMessage(json_encode($job));
            $channel->basic_publish($message, '', $queue);
            $this->logger->info("Job pushed to queue: {$queue}");
        } catch (AMQPRuntimeException $e) {
            $this->logger->error("Failed to push job to queue: {$queue}", ['exception' => $e]);
            // Consider whether throwing an exception here is necessary
        }
    }

    public function pop(string $queue): ?array
    {
        try {
            $channel = $this->getChannel();
            $channel->queue_declare($queue, self::QUEUE_PASSIVE, self::QUEUE_DURABLE, self::QUEUE_EXCLUSIVE, self::QUEUE_AUTO_DELETE);
            $message = $channel->basic_get($queue);
            if ($message === null) {
                return null;
            }
            $channel->basic_ack($message->getDeliveryTag());
            $this->logger->info("Job popped from queue: {$queue}");
            return $this->createJobFromMessage($message->getBody());
        } catch (AMQPTimeoutException $e) {
            $this->logger->warning("Timeout while trying to pop job from queue: {$queue}", ['exception' => $e]);
            return null;
        } catch (AMQPRuntimeException $e) {
            $this->logger->error("Failed to pop job from queue: {$queue}", ['exception' => $e]);
            return null;
        }
    }

    private function createJobFromMessage(string $messageBody): ?array
    {
        return json_decode($messageBody, true);
    }

    public function size(string $queue): int
    {
        try {
            $channel = $this->getChannel();
            $declared = $channel->queue_declare($queue, self::QUEUE_PASSIVE, self::QUEUE_DURABLE, self::QUEUE_EXCLUSIVE, self::QUEUE_AUTO_DELETE);
            return $declared[1];
        } catch (AMQPRuntimeException $e) {
            $this->logger->error("Failed to get queue size: {$queue}", ['exception' => $e]);
            return 0;
        }
    }

    public function clear(string $queue): void
    {
        try {
            $channel = $this->getChannel();
            $channel->queue_purge($queue);
            $this->logger->info("Queue cleared: {$queue}");
        } catch (AMQPRuntimeException $e) {
            $this->logger->error("Failed to clear queue: {$queue}", ['exception' => $e]);
        }
    }

    public function __destruct()
    {
        if ($this->channel !== null && $this->channel->is_open()) {
            $this->channel->close();
        }
        if ($this->connection->isConnected()) {
            $this->connection->close();
        }
    }
}