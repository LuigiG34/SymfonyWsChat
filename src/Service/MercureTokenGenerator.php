<?php

namespace App\Service;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;

class MercureTokenGenerator
{
    private string $mercureSecret;

    public function __construct(string $mercureSecret)
    {
        $this->mercureSecret = $mercureSecret;
    }

    public function generateToken(string $username): string
    {
        $config = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($this->mercureSecret)
        );

        $now = new \DateTimeImmutable();
        
        $token = $config->builder()
            ->issuedAt($now)
            ->expiresAt($now->modify('+1 hour'))
            ->withClaim('mercure', [
                'subscribe' => ['chat/' . $username]
            ])
            ->getToken($config->signer(), $config->signingKey());

        return $token->toString();
    }
}