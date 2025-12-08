<?php

namespace Mita\UranusHttpServer\Middlewares;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;

interface CsrfMiddlewareInterface extends MiddlewareInterface
{
    public function generateToken(ServerRequestInterface $request): string;
}
