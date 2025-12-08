<?php 
namespace Mita\UranusHttpServer\Cache;

use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Psr16Cache;

class CacheManager
{
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getCacheDriver(): CacheInterface
    {
        $driver = $this->config['driver'] ?? 'file';

        switch ($driver) {
            case 'redis':
                $url = 'redis://' . $this->config['redis']['host'] . ':' . $this->config['redis']['port'];
                $options = [];
                if ($this->config['redis']['password']) {
                    $options['password'] = $this->config['redis']['password'];
                }
                if ($this->config['redis']['database']) {
                    $options['database'] = $this->config['redis']['database'];
                }
                $redisConnection = RedisAdapter::createConnection($url);
                $psr6Cache = new RedisAdapter($redisConnection);
                break;

            case 'apcu':
                $psr6Cache = new ApcuAdapter($this->config['apcu']['namespace']);
                break;
                
            case 'file':
            default:
                $psr6Cache = new FilesystemAdapter('', 0, $this->config['file']['path']);
                break;
        }

        return new Psr16Cache($psr6Cache);
    }
}
