<?php 
namespace Mita\UranusHttpServer\Middlewares;

use Psr\SimpleCache\CacheInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpException;

class RateLimitMiddleware implements MiddlewareInterface
{
    protected CacheInterface $cache;
    protected int $limit;
    protected int $timeWindow;

    public function __construct(CacheInterface $cache, int $limit = 30, int $timeWindow = 60)
    {
        $this->cache = $cache;
        $this->limit = $limit;
        $this->timeWindow = $timeWindow;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $identifier = $this->getIdentifier($request);
        $key = "rate_limit-{$identifier}";

        $current = $this->cache->get($key, 0);

        if ($current >= $this->limit) {
            $this->createRateLimitErrorResponse($request);
        }

        $this->incrementRequestCount($key, $current);

        $request = $request->withAttribute('rate_limit', [
            'limit' => $this->limit,
            'remaining' => $this->limit - $current - 1,
            'reset' => time() + $this->timeWindow,
        ]);

        return $handler->handle($request);
    }

    protected function getIdentifier(ServerRequestInterface $request): string
    {
        return $request->getServerParams()['REMOTE_ADDR'];
    }

    protected function incrementRequestCount(string $key, int $current): void
    {
        $this->cache->set($key, $current + 1, $this->timeWindow);
    }

    protected function createRateLimitErrorResponse(ServerRequestInterface $request): void
    {
        throw new HttpException($request, 'Rate limit exceeded', 429);
    }
}
