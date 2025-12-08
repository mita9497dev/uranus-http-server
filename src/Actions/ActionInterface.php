<?php 
namespace Mita\UranusHttpServer\Actions;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface ActionInterface
{
    public function validate(ServerRequestInterface $request): bool;

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface;
}