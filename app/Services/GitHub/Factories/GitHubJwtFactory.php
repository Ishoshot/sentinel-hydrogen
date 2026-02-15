<?php

declare(strict_types=1);

namespace App\Services\GitHub\Factories;

use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use RuntimeException;

/**
 * Generates JWTs for GitHub App authentication using the app's private key.
 */
final readonly class GitHubJwtFactory
{
    /**
     * Generate a JWT for GitHub App authentication.
     *
     * @throws RuntimeException If the private key cannot be read or is empty
     */
    public function generate(): string
    {
        $privateKey = $this->getPrivateKey();

        if ($privateKey === '') {
            throw new RuntimeException('GitHub App private key is empty');
        }

        $config = Configuration::forAsymmetricSigner(
            new Sha256,
            InMemory::plainText($privateKey),
            InMemory::plainText($privateKey)
        );

        $now = new DateTimeImmutable('@'.time());
        $exp = new DateTimeImmutable('@'.(time() + 600));

        /** @var string|int $appIdRaw */
        $appIdRaw = config('github.app_id');

        /** @var non-empty-string $appId */
        $appId = (string) $appIdRaw;

        $token = $config->builder()
            ->issuedBy($appId)
            ->issuedAt($now)
            ->expiresAt($exp)
            ->getToken($config->signer(), $config->signingKey());

        return $token->toString();
    }

    /**
     * Get the private key from environment variable or file.
     *
     * @throws RuntimeException If the private key cannot be read
     */
    private function getPrivateKey(): string
    {
        $privateKeyFromEnv = config('github.private_key');
        if (is_string($privateKeyFromEnv) && $privateKeyFromEnv !== '') {
            return $privateKeyFromEnv;
        }

        /** @var string $configPath */
        $configPath = config('github.private_key_path');
        $privateKeyPath = str_starts_with($configPath, '/') ? $configPath : base_path($configPath);

        if (! file_exists($privateKeyPath)) {
            Log::error('GitHub App private key not found', ['path' => $privateKeyPath]);

            throw new RuntimeException('GitHub App private key not found at: '.$privateKeyPath);
        }

        $privateKey = file_get_contents($privateKeyPath);

        if ($privateKey === false || $privateKey === '') {
            Log::error('Failed to read GitHub App private key', ['path' => $privateKeyPath]);

            throw new RuntimeException('Failed to read GitHub App private key');
        }

        return $privateKey;
    }
}
