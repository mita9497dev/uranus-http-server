<?php 
namespace Mita\UranusHttpServer\Exceptions;

use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;

class CsrfInvalidException extends HttpException
{
    public function __construct(ServerRequestInterface $request, $message = 'CSRF token is invalid', $code = 500, $previous = null)
    {
        parent::__construct($request, $message, $code, $previous);
    }
}