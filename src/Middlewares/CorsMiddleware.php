<?php 
namespace Mita\UranusHttpServer\Middlewares;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Routing\RouteContext;

final class CorsMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routeContext = RouteContext::fromRequest($request);
        $routingResults = $routeContext->getRoutingResults();
        $methods = $routingResults->getAllowedMethods();
        $requestHeaders = $request->getHeaderLine('Access-Control-Request-Headers');

        if ($request->getMethod() === 'OPTIONS') {
            $response = new Response();
            return $response
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Methods', implode(',', $methods))
                ->withHeader('Access-Control-Allow-Headers', $requestHeaders)
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withStatus(200);
        }

        $response = $handler->handle($request);

        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', implode(',', $methods))
            ->withHeader('Access-Control-Allow-Headers', $requestHeaders)
            ->withHeader('Access-Control-Allow-Credentials', 'true');
    }
}