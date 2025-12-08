<?php 
namespace Mita\UranusHttpServer\Queue;

use Mita\UranusHttpServer\Configs\Config;
use Psr\Log\LoggerInterface;

class QueueManager
{
    protected Config $config;
    protected LoggerInterface $logger;

    public function __construct(Config $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function getQueue(): QueueInterface
    {
        $driver = $this->config->get('queue.driver', 'rabbitmq');
        $config = $this->config->get("queue.connections.$driver");

        switch ($driver) {
            case 'rabbitmq':
                return new RabbitMQQueue($config, $this->logger);
            default:
                throw new \InvalidArgumentException("Unsupported queue driver: $driver");
        }
    }
}
