<?php 
namespace Mita\UranusHttpServer\Actions;

use Mita\UranusHttpServer\Auth\DefaultAuthenticatable;
use Mita\UranusHttpServer\Auth\DefaultAuthPayload;
use Mita\UranusHttpServer\Contracts\AuthenticatableInterface;
use Mita\UranusHttpServer\Models\HasUserAccessScopeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AbstractAction implements ActionInterface, AuthorizableInterface
{
    public const POLICY_NAME = null;
    
    public const ACCEPT_ROLES = null;

    public const AUTH_PAYLOAD_CLASS = null;

    protected static AuthenticatableInterface $authenticatable;

    public static function getAuthPayloadClass(): ?string
    {
        return static::AUTH_PAYLOAD_CLASS ?? DefaultAuthPayload::class;
    }

    public function getAuthenticatable(ServerRequestInterface $request): AuthenticatableInterface
    {
        /** @var \Mita\UranusHttpServer\Contracts\AuthenticatableInterface $authenticatable */
        $authenticatable = $request->getAttribute('__authenticatable') ?? new DefaultAuthenticatable();
        return $authenticatable;
    }

    public function authorize(string $userRole, array $userPolicies = []): bool
    {
        if ($this->checkRoleAccess($userRole, $userPolicies)) {
            return true;
        }

        return true;
    }

    public function authorizeOwnerShip($ownerId, HasUserAccessScopeInterface $resource): bool
    {
        return true;
    }

    public function checkRoleAccess(string $userRole, array $userPolicies = []): bool
    {
        $roles = static::ACCEPT_ROLES ?? [];

        if ($roles === null || $roles === true) {
            return true;
        }

        if ($roles === false) {
            return false;
        }

        if ($this->checkRole($userRole, $roles)) {
            return true;
        }

        if (in_array(static::POLICY_NAME, $userPolicies)) {
            return true;
        }

        return false;
    }

    public function checkRole(string $userRole, array $acceptRoles): bool
    {
        if (in_array('!' . $userRole, $acceptRoles)) {
            return false;
        }

        if (in_array($userRole, $acceptRoles)) {
            return true;
        }

        return false;
    }

    public function getPolicy(): ?callable
    {
        return [];
    }

    public function validate(ServerRequestInterface $request): bool
    {
        return true;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $response;
    }
}
