<?php 
namespace Mita\UranusHttpServer\Handlers;

use Psr\Http\Message\ResponseInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpNotImplementedException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Handlers\ErrorHandler;
use Exception;
use Mita\UranusHttpServer\Actions\HtmlResponseInterface;
use Mita\UranusHttpServer\Exceptions\CsrfInvalidException;
use Slim\Routing\RouteContext;
use Throwable;
use Uranus\HttpServer\Exceptions\InvalidTransformDataException;

class HttpErrorHandler extends ErrorHandler
{
    public const BAD_REQUEST = 'BAD_REQUEST';
    public const INSUFFICIENT_PRIVILEGES = 'INSUFFICIENT_PRIVILEGES';
    public const NOT_ALLOWED = 'NOT_ALLOWED';
    public const NOT_IMPLEMENTED = 'NOT_IMPLEMENTED';
    public const RESOURCE_NOT_FOUND = 'RESOURCE_NOT_FOUND';
    public const SERVER_ERROR = 'SERVER_ERROR';
    public const UNAUTHENTICATED = 'UNAUTHENTICATED';
    public const CSRF_INVALID = 'CSRF_INVALID';
    
    public bool $sentryLog = false;
    public bool $debug = false;

    public function setSentryLog(bool $sentryLog) 
    {
        $this->sentryLog = $sentryLog;
        return $this;
    }

    public function setDebug(bool $debug) 
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * Write to the error log if $logErrors has been set to true
     *
     * @return void
     */
    protected function writeToErrorLog(): void
    {
        if ($this->debug) {
            throw $this->exception;
        }
        $renderer = $this->callableResolver->resolve($this->logErrorRenderer);
        $error = $renderer($this->exception, $this->logErrorDetails);
        $this->logError($error);

        if ($this->sentryLog) {
            \Sentry\captureException($this->exception);
        }
    }

    /**
     * Wraps the error_log function so that this can be easily tested
     *
     * @param string $error
     * @return void
     */
    protected function logError(string $error): void
    {
        if ($this->logErrors) {
            $this->logger->error($error);
        }
    }
    
    protected function respond(): ResponseInterface
    {
        $exception = $this->exception;
        $statusCode = 500;
        $type = self::SERVER_ERROR;
        $description = 'An internal error has occurred while processing your request.';

        if ($exception instanceof HttpException) {
            $statusCode = $exception->getCode();
            $description = $exception->getMessage();

            if ($exception instanceof HttpNotFoundException) {
                $type = self::RESOURCE_NOT_FOUND;
            } elseif ($exception instanceof HttpMethodNotAllowedException) {
                $type = self::NOT_ALLOWED;
            } elseif ($exception instanceof HttpUnauthorizedException) {
                $type = self::UNAUTHENTICATED;
            } elseif ($exception instanceof HttpForbiddenException) {
                $type = self::UNAUTHENTICATED;
            } elseif ($exception instanceof HttpBadRequestException) {
                $type = self::BAD_REQUEST;
            } elseif ($exception instanceof HttpNotImplementedException) {
                $type = self::NOT_IMPLEMENTED;
            } elseif ($exception instanceof CsrfInvalidException) {
                $type = self::CSRF_INVALID;
            } elseif ($exception instanceof InvalidTransformDataException) {
                $type = self::BAD_REQUEST;
            }
        }

        if (!($exception instanceof HttpException) && ($exception instanceof Exception || $exception instanceof Throwable)) {
            if ($this->displayErrorDetails) {
                $description = $exception->getMessage();    
            }
        }
        
        $error = [
            'status' => 0,
            'data'   => [], 
            'errors' => [
                'type'          => $type,
                'description'   => $description,
            ]
        ];

        $routeContext = RouteContext::fromRequest($this->request);
        $route = $routeContext->getRoute();
        if ($route) {
            $callable = $route->getCallable();
            if (is_string($callable) && class_exists($callable)) {
                $action = new \ReflectionClass($callable);
                if ($action->implementsInterface(HtmlResponseInterface::class)) {
                    $payload = '<h1>500 Internal Server Error</h1>';
                    $response = $this->responseFactory->createResponse($statusCode);
                    $response->getBody()->write($payload);
                    return $response;
                }
            }
        }
        
        $payload = json_encode($error, JSON_PRETTY_PRINT);
        $response = $this->responseFactory->createResponse($statusCode);        
        $response->getBody()->write($payload);
        
        return $response;
    }
}
