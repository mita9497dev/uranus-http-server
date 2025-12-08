<?php namespace Mita\UranusHttpServer\Auth;

use Mita\UranusHttpServer\Contracts\AuthenticatableInterface;

class DefaultAuthenticatable implements AuthenticatableInterface
{
    public function getAuthIdentifier(): string
    {
        return '';
    }

    public function getAuthPayload(): array
    {
        return [];
    }

    public function getRole(): string
    {
        return '';
    }

    public function getPolicies(): array
    {
        return [];
    }
}
