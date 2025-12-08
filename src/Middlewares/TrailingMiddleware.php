<?php 
namespace Mita\UranusHttpServer\Middlewares;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class TrailingMiddleware implements MiddlewareInterface
{
    private $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri();
        $path = $uri->getPath();

        if ($this->basePath != '/') {
            $this->basePath .= '/';
        }

        // echo $this->basePath;

        if ($path != $this->basePath && substr($path, -1) == '/') {
            $path = rtrim($path, '/');
            $uri = $uri->withPath($path);
            
            if ($request->getMethod() == 'GET') {
                $response = new Response();
                return $response
                    ->withHeader('Location', (string) $uri)
                    ->withStatus(302);
            } else {
                $request = $request->withUri($uri);
            }
        }

        return $handler->handle($request);
    }
}