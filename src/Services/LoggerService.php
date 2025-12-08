<?php 
namespace Mita\UranusHttpServer\Services;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class LoggerService 
{
    private string $path;
    
    private string $name;

    private int $level;

    /**
     * @var array Handler
     */
    private $handler = [];

    public function __construct(string $basePath, string $name, int $level)
    {
        $this->path = $basePath;
        $this->name = $name;
        $this->level = $level;
    }

    /**
     * Add rotating file logger handler.
     *
     * @param string $filename The filename
     * @param int $level The level (optional)
     *
     * @return self The logger factory
     */
    public function addFileHandler(string $filename, int $level = null, string $path = ''): self
    {
        if ($path != '') {
            $filename = sprintf('%s/%s/%s', $this->path, $path, $filename);

        } else {
            $filename = sprintf('%s/%s', $this->path, $filename);
        }
        $rotatingFileHandler = new RotatingFileHandler($filename, 0, $level ?? $this->level, true, 0777);

        // The last "true" here tells monolog to remove empty []'s
        $rotatingFileHandler->setFormatter(new LineFormatter(null, null, false, true));

        $this->handler[] = $rotatingFileHandler;

        return $this;
    }

    /**
     * Add a console logger.
     *
     * @param int $level The level (optional)
     *
     * @return self The instance
     */
    public function addConsoleHandler(?int $level = null): self
    {
        $streamHandler = new StreamHandler('php://stdout', $level ?? $this->level);
        $streamHandler->setFormatter(new LineFormatter(null, null, false, true));

        $this->handler[] = $streamHandler;

        return $this;
    }

    /**
     * Build the logger.
     *
     * @param string $name The name
     *
     * @return LoggerInterface The logger
     */
    public function createInstance(string $name): LoggerInterface
    {
        $logger = new Logger($name);

        foreach ($this->handler as $handler) {
            $logger->pushHandler($handler);
        }

        $this->handler = [];

        return $logger;
    }
}