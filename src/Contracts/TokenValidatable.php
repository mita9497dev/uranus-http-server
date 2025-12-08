<?php namespace Mita\UranusHttpServer\Contracts;

interface TokenValidatable 
{
    /**
     * Validate token from database
     * 
     * @param string $token
     * @return bool
     */
    public function validateToken(string $token): bool;

    /**
     * Get login record by token
     * 
     * @param string $token  
     * @return mixed
     */
    public function getLoginByToken(string $token);
}
