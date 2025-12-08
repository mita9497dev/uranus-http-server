<?php

namespace Mita\UranusHttpServer\Middlewares;

use Mita\UranusHttpServer\Actions\GlobalVariableProviderInterface;
use Mita\UranusHttpServer\Services\GlobalVariableService;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Routing\RouteContext;

class ViewGlobalVariableMiddleware implements MiddlewareInterface
{
    private $globalVariablesService;
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container, GlobalVariableService $globalVariablesService)
    {
        $this->container = $container;
        $this->globalVariablesService = $globalVariablesService;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->addGlobalVariables($request);

        return $handler->handle($request);
    }

    private function addGlobalVariables(ServerRequestInterface $request): void
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        if ($route) {
            $callable = $route->getCallable();

            if (is_string($callable) && class_exists($callable)) {
                $action = $this->container->get($callable);
                if ($action instanceof GlobalVariableProviderInterface) {
                    $this->globalVariablesService->loadForAction($action);
                }

            } elseif (is_object($callable) && $callable instanceof GlobalVariableProviderInterface) {
                $this->globalVariablesService->loadForAction($callable);
            }
        }
    }
}
