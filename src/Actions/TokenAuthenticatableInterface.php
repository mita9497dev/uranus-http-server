<?php namespace Mita\UranusHttpServer\Actions;

use Psr\Http\Message\ServerRequestInterface;

interface TokenAuthenticatableInterface
{
    /**
     * Get the token from request
     * 
     * @return string|null
     */
    public function getToken(ServerRequestInterface $request): ?string;
}
