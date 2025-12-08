<?php

namespace Mita\UranusHttpServer\Middlewares;

use Mita\UranusHttpServer\Exceptions\CsrfInvalidException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SlimSession\Helper;

class CsrfMiddleware implements CsrfMiddlewareInterface
{
    protected string $tokenKey = '_csrf_token';
    protected string $sessionKey = '_csrf_token';
    protected string $lifetime;
    protected Helper $session;

    public function __construct(
        Helper $session, 
        string $lifetime = '+1 hour', 
        string $tokenKey = '_csrf_token', 
        string $sessionKey = '_csrf_token'
    )
    {
        $this->session = $session;
        $this->lifetime = $lifetime;
        $this->tokenKey = $tokenKey;
        $this->sessionKey = $sessionKey;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (in_array(strtoupper($request->getMethod()), ['GET', 'HEAD', 'OPTIONS'])) {
            return $handler->handle($request);
        }

        $token = $request->getParsedBody()[$this->tokenKey] ?? null;
        $sessionToken = $this->session->get($this->sessionKey);

        if (!$token || !$sessionToken || !hash_equals($sessionToken, $token)) {
            return $this->createCsrfErrorResponse($request);
        }

        if ($this->isExpired($request)) {
            return $this->createCsrfErrorResponse($request);
        }

        $request = $request->withAttribute('csrf_token', $token);

        return $handler->handle($request);
    }

    public function generateToken(ServerRequestInterface $request): string
    {
        /** @var Helper */
        $session = $request->getAttribute('session');
        $token = bin2hex(random_bytes(32));
        $session->set($this->sessionKey, $token);
        $session->set($this->sessionKey . '_expires', time() + strtotime($this->lifetime));
        return $token;
    }

    public function isExpired(ServerRequestInterface $request): bool
    {
        /** @var Helper */
        $session = $request->getAttribute('session');
        $expires = $session->get($this->sessionKey . '_expires');
        return $expires < time();
    }

    protected function createCsrfErrorResponse(ServerRequestInterface $request): ResponseInterface
    {
        throw new CsrfInvalidException($request, 'CSRF token is invalid', 403);
    }
}
