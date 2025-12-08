<?php 
namespace Mita\UranusHttpServer\Services;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\Clock\SystemClock;
use DateTimeImmutable;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;

class JWTService implements JWTServiceInterface
{
    private $config;
    private $accessTokenOptions;

    public function __construct(string $secretKey, string $algorithm, array $accessTokenOptions)
    {
        $algorithm = strtolower($algorithm);
        switch ($algorithm) {
            case 'hs256':
                $signer = new Sha256();
                break;
            default:
                $signer = new Sha256();
                break;
        }

        $this->config = Configuration::forSymmetricSigner(
            $signer,
            InMemory::plainText($secretKey)
        );
        $this->accessTokenOptions = $accessTokenOptions;
    }

    public function getExpiresAt(): string
    {
        return $this->accessTokenOptions['expires_at'];
    }

    public function getTokenExpiresAt(): DateTimeImmutable
    {
        return (new DateTimeImmutable())->modify($this->getExpiresAt());
    }
    
    public function generateToken(array $payload): string
    {
        $now = new DateTimeImmutable();
        $token = $this->config->builder()
            ->issuedBy($this->accessTokenOptions['issued_by'])
            ->permittedFor($this->accessTokenOptions['permitted_for'])
            ->issuedAt($now)
            ->expiresAt($now->modify($this->accessTokenOptions['expires_at']))
            ->withClaim('data', $payload)
            ->getToken($this->config->signer(), $this->config->signingKey());

        return $token->toString();
    }

    public function validateToken(string $token): bool
    {
        try {
            $token = $this->config->parser()->parse($token);
            $constraints = [
                new SignedWith($this->config->signer(), $this->config->signingKey()),
                new LooseValidAt(SystemClock::fromUTC())
            ];

            return $this->config->validator()->validate($token, ...$constraints);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function decodeToken(string $token): array
    {
        try {
            /** @var \Lcobucci\JWT\Token\Plain $jwt */
            $jwt = $this->config->parser()->parse($token);

            return $jwt->claims()->all();
        } catch (\Exception $e) {
            return [];
        }
    }

    public function generateRefreshToken(string $userId): string
    {
        $now = new DateTimeImmutable();
        $token = $this->config->builder()
            ->issuedBy($this->accessTokenOptions['issued_by'])
            ->permittedFor($this->accessTokenOptions['permitted_for'])
            ->issuedAt($now)
            ->expiresAt($now->modify($this->accessTokenOptions['expires_at']))
            ->withClaim('uid', $userId)
            ->getToken($this->config->signer(), $this->config->signingKey());

        return $token->toString();
    }

    public function validateRefreshToken(string $token): bool
    {
        return $this->validateToken($token);
    }

    public function verifyRefreshToken(string $token): array
    {
        return $this->verifyToken($token);
    }

    public function verifyToken(string $token): array
    {
        return $this->decodeToken($token);
    }

    public function setPermittedFor(string $permittedFor): void
    {
        $this->accessTokenOptions['permitted_for'] = $permittedFor;
    }

    public function setIssuedBy(string $issuedBy): void
    {
        $this->accessTokenOptions['issued_by'] = $issuedBy;
    }

    public function setExpiresAt(string $expiresAt): void
    {
        $this->accessTokenOptions['expires_at'] = $expiresAt;
    }
}
