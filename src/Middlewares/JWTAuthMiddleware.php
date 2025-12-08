<?php 
namespace Mita\UranusHttpServer\Middlewares;

use Carbon\Carbon;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Exception;
use Mita\UranusHttpServer\Actions\AuthorizableInterface;
use Mita\UranusHttpServer\Contracts\TokenValidatable;
use Mita\UranusHttpServer\Services\JWTServiceInterface;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Routing\RouteContext;

class JWTAuthMiddleware implements JWTAuthMiddlewareInterface
{
    private $jwtService;
    private $queryKey = 'access_token';
    private $headerKey = 'Authorization';
    
    private $guards = [];

    public function __construct(JWTServiceInterface $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    public function setQueryKey(string $queryKey): void
    {
        $this->queryKey = $queryKey;
    }

    public function setHeaderKey(string $headerKey): void
    {
        $this->headerKey = $headerKey;
    }

    public function addGuard(string $name, string $payloadClass): void
    {
        $this->guards[$name] = $payloadClass;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->getTokenFromRequest($request);
        if (!$token) {
            throw new HttpUnauthorizedException($request, 'Invalid or expired token');
        }

        $payload = $this->jwtService->decodeToken($token);

        if (!$payload) {
            throw new HttpUnauthorizedException($request, 'Invalid or expired token');
        }

        $route = RouteContext::fromRequest($request)->getRoute();
        $callable = $route->getCallable();
        if (is_subclass_of($callable, AuthorizableInterface::class)) {
            $payloadClass = $callable::getAuthPayloadClass();
            if ($payloadClass) {
                /** @var \Mita\UranusHttpServer\Auth\AbstractAuthPayload $payloadHandler */
                $payloadHandler = new $payloadClass();

                /** @var \Mita\UranusHttpServer\Contracts\AuthenticatableInterface $authenticatable */
                $authenticatable = $payloadHandler->fromArray($payload['data']);

                if ($authenticatable instanceof TokenValidatable) {
                    $request = $request->withAttribute('__authenticatable', $authenticatable);
                    $request = $request->withAttribute('__token', $token);

                    if (!($login = $authenticatable->getLoginByToken($token))) {
                        throw new HttpUnauthorizedException($request, 'Invalid or expired token');
                    }
                    
                    $login->last_activity = Carbon::now();
                    $login->save();
                } else {
                    throw new HttpUnauthorizedException($request, 'Invalid or expired token');
                }
            }
        }

        return $handler->handle($request);
    }

    private function getTokenFromRequest(ServerRequestInterface $request): ?string
    {
        $queryParams = $request->getQueryParams();
        if (isset($queryParams[$this->queryKey])) {
            return $queryParams[$this->queryKey];
        }

        $authHeader = $request->getHeaderLine($this->headerKey);
        if (strpos($authHeader, 'Bearer ') === 0) {
            return substr($authHeader, 7);
        }

        return null;
    }
}
