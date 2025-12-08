<?php namespace Mita\UranusHttpServer\Auth;

use Mita\UranusHttpServer\Contracts\AuthenticatableInterface;

class DefaultAuthPayload implements AbstractAuthPayload
{
    public function toArray(): array
    {
        return [];
    }

    public function fromArray(array $payload): AuthenticatableInterface
    {
        return new DefaultAuthenticatable();
    }
}
