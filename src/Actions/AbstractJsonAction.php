<?php 

namespace Mita\UranusHttpServer\Actions;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class AbstractJsonAction extends AbstractAction implements 
    JsonResponseInterface, 
    ActionInterface, 
    AuthorizableInterface,
    TokenAuthenticatableInterface
{
    public function getToken(ServerRequestInterface $request): ?string
    {
        return $request->getAttribute('__token');
    }

    public function validate(ServerRequestInterface $request, array $args = []): bool
    {
        return true;
    }

    public function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $formattedData = $this->formatSuccessResponse($data);
        $response->getBody()->write(json_encode($formattedData, JSON_UNESCAPED_UNICODE));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    public function jsonCustom(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    public function error(ResponseInterface $response, $messages, int $status = 500): ResponseInterface
    {
        $errorData = $this->formatErrorResponse($messages);
        $response->getBody()->write(json_encode($errorData, JSON_UNESCAPED_UNICODE));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    public function validationError(ResponseInterface $response, array $errors, int $status = 422): ResponseInterface
    {
        $errorData = $this->formatValidationErrorResponse($errors);
        $response->getBody()->write(json_encode($errorData, JSON_UNESCAPED_UNICODE));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    /**
     * Customize this method to define the error response structure.
     *
     * @param mixed $messages
     * @return array
     */
    protected function formatErrorResponse($messages): array
    {
        return [
            'status' => 0, 
            'data'   => [],
            'errors' => $messages,
        ];
    }

    /**
     * Customize this method to define the success response structure.
     *
     * @param array $data
     * @return array
     */
    protected function formatSuccessResponse(array $data): array
    {
        return [
            'status' => 1,
            'data'   => $data,
            'errors' => [],
        ];
    }

    /**
     * Customize this method to define the validation error response structure.
     *
     * @param array $errors
     * @return array
     */
    protected function formatValidationErrorResponse(array $errors): array
    {
        $errors = [];
        foreach ($errors as $field => $message) {
            $errors[$field] = $message;
        }

        return [
            'status' => 0, 
            'data'   => [],
            'errors' => $errors,
        ];
    }
}