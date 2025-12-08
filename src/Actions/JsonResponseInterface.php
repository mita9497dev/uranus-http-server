<?php 
namespace Mita\UranusHttpServer\Actions;

use Psr\Http\Message\ResponseInterface;

interface JsonResponseInterface
{
    public function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface;
}