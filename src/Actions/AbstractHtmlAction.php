<?php 

namespace Mita\UranusHttpServer\Actions;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

abstract class AbstractHtmlAction extends AbstractAction implements 
    HtmlResponseInterface, 
    ActionInterface, 
    GlobalVariableProviderInterface, 
    AuthorizableInterface
{
    protected Twig $twig;

    public const TEMPLATE = '';

    public const ACTIVE_NAVBAR = '';

    public function validate(ServerRequestInterface $request): bool
    {
        return true;
    }

    public function __construct(Twig $twig)
    {
        $this->twig = $twig;
    }

    public function html(ResponseInterface $response, int $status, array $data): ResponseInterface
    {
        $formattedData = $this->formatSuccessResponse($data);
        return $this->twig->render($response, static::TEMPLATE, $formattedData)->withStatus($status);
    }

    public function redirect(ResponseInterface $response, string $url, int $status = 302): ResponseInterface
    {
        return $response->withHeader('Location', $url)->withStatus($status);
    }

    public function redirectRoute(
        ServerRequestInterface $request, 
        ResponseInterface $response, 
        string $routeName, 
        array $params = [], 
        array $queryParams = [], 
        int $status = 302
    ): ResponseInterface
    {
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();
        return $this->redirect($response, $routeParser->urlFor($routeName, $params, $queryParams), $status);
    }

    public function notFound(ResponseInterface $response): ResponseInterface
    {
        return $this->error($response, ['Not Found'], 404);
    }

    public function error(ResponseInterface $response, array $errors, int $status = 500): ResponseInterface
    {
        $errorData = $this->formatErrorResponse($errors);
        return $this->html($response, $status, $errorData);
    }

    /**
     * Customize this method to define the success response structure.
     *
     * @param array $data
     * @return array
     */
    protected function formatSuccessResponse(array $data): array
    {
        return array_merge($this->getGlobalVariables(), $data);
    }

    /**
     * Customize this method to define the error response structure.
     *
     * @param array $errors
     * @return array
     */
    protected function formatErrorResponse(array $errors): array
    {
        return array_merge($this->getGlobalVariables(), [
            'errors' => $errors
        ]);
    }

    public function getGlobalVariables(): array
    {
        return [
            'activeNavbar' => static::ACTIVE_NAVBAR,  
        ];
    }
}
