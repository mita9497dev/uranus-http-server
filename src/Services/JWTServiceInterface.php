<?php 
namespace Mita\UranusHttpServer\Services;

use DateTimeImmutable;

interface JWTServiceInterface
{
    public function generateToken(array $payload): string;
    public function validateToken(string $token): bool;
    public function decodeToken(string $token): array;
    public function generateRefreshToken(string $userId): string;
    public function validateRefreshToken(string $token): bool;
    public function verifyRefreshToken(string $token): array;
    public function setPermittedFor(string $permittedFor): void;
    public function setIssuedBy(string $issuedBy): void;
    public function getTokenExpiresAt(): DateTimeImmutable;
    public function setExpiresAt(string $expiresAt): void;
}
