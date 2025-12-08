<?php namespace Mita\UranusHttpServer\Contracts;

interface AuthenticatableInterface
{
    public function getAuthIdentifier();
    public function getAuthPayload(): array;
    public function getRole(): string;
    public function getPolicies(): array;
}
