<?php namespace Mita\UranusHttpServer\Auth;

use Mita\UranusHttpServer\Contracts\AuthenticatableInterface;

abstract class AbstractAuthPayload
{
    protected $model;

    public function __construct(?AuthenticatableInterface $model = null)
    {
        $this->model = $model;
    }

    abstract public function toArray(): array;
    abstract public function fromArray(array $payload): AuthenticatableInterface;
}
