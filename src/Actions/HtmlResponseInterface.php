<?php 
namespace Mita\UranusHttpServer\Actions;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface HtmlResponseInterface
{
    public function html(ResponseInterface $response, int $status, array $data): ResponseInterface;

    public function redirect(ResponseInterface $response, string $url, int $status = 302): ResponseInterface;

    public function redirectRoute(
        ServerRequestInterface $request, ResponseInterface $response, string $routeName, array $params = [], array $queryParams = [], int $status = 302): ResponseInterface;

    public function notFound(ResponseInterface $response): ResponseInterface;

    public function error(ResponseInterface $response, array $errors, int $status = 500): ResponseInterface;

}