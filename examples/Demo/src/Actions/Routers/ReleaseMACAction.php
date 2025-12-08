<?php
namespace Mita\HttpServerDemo\Actions\Routers;

use Mita\UranusHttpServer\Actions\AbstractJsonAction;
use Mita\UranusHttpServer\Actions\AuthorizableInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;


class ReleaseMACAction extends AbstractJsonAction implements AuthorizableInterface
{
    public const POLICY_NAME = null;

    public const ACCEPT_ROLES = null;

    public function __invoke(RequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        // Implement your action logic here
        return $this->json($response, [
            'message' => 'Hello from ReleaseMACAction'
        ]);
    }


}