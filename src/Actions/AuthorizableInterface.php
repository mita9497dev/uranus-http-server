<?php 
namespace Mita\UranusHttpServer\Actions;

use Mita\UranusHttpServer\Auth\AbstractAuthPayload;
use Mita\UranusHttpServer\Contracts\AuthenticatableInterface;
use Mita\UranusHttpServer\Models\HasUserAccessScopeInterface;
use Psr\Http\Message\ServerRequestInterface;

interface AuthorizableInterface 
{
    /**
     * Lấy class payload từ action.
     * 
     * @return string|null
     */
    public static function getAuthPayloadClass();

    /**
     * Lấy payload từ action.
     * 
     * @return AuthenticatableInterface
     */
    public function getAuthenticatable(ServerRequestInterface $request): AuthenticatableInterface;
    
    /**
     * Kiểm tra quyền truy cập dựa trên các thuộc tính người dùng.
     *
     * @param string $role
     * @param array $userPolicies
     * 
     * @return bool
     */
    public function authorize(string $role, array $userPolicies): bool;

    /**
     * Kiểm tra quyền sở hữu tài nguyên, mặc định trả về true.
     *
     * @param string|int $ownerId
     * @param HasUserAccessScopeInterface $resource
     * @return bool
     */
    public function authorizeOwnerShip($ownerId, HasUserAccessScopeInterface $resource): bool;

    /**
     * Lấy policy từ service, nếu được định nghĩa.
     *
     * @return callable|null
     */
    public function getPolicy(): ?callable;

    /**
     * Kiểm tra quyền dựa trên ACCEPT_ROLES.
     *
     * @param string $role
     * @return bool
     */
    public function checkRoleAccess(string $role): bool;

    /**
     * Kiểm tra quyền dựa trên ACCEPT_ROLES.
     *
     * @param string $role
     * @param array $acceptRoles
     * @return bool
     */
    public function checkRole(string $role, array $acceptRoles): bool;
}
