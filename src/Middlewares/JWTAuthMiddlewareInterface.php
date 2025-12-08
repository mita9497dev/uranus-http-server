<?php 
namespace Mita\UranusHttpServer\Middlewares;

use Psr\Http\Server\MiddlewareInterface;

interface JWTAuthMiddlewareInterface extends MiddlewareInterface
{
    public function setQueryKey(string $queryKey): void;
    public function setHeaderKey(string $headerKey): void;

    public function addGuard(string $name, string $payloadClass): void;
}
