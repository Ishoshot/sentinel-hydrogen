<?php

declare(strict_types=1);

namespace App\Services\GitHub;

use App\Services\GitHub\Contracts\GitHubAppServiceContract;
use DateTimeImmutable;
use GrahamCampbell\GitHub\GitHubManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use RuntimeException;

final readonly class GitHubAppService implements GitHubAppServiceContract
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private GitHubManager $github
    ) {}

    /**
     * Generate a JWT for GitHub App authentication.
     *
     * @return string The JWT token
     *
     * @throws RuntimeException If the private key cannot be read
     */
    public function generateJwt(): string
    {
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
     * Get an installation access token (cached for 50 minutes).
     *
     * @param  int  $installationId  The GitHub App installation ID
     * @return string The installation access token
     */
    public function getInstallationToken(int $installationId): string
    {
        $cacheKey = 'github_installation_token_'.$installationId;

        /** @var int $ttl */
        $ttl = config('github.token_cache_ttl', 3000);

        /** @var string $cachedToken */
        $cachedToken = Cache::remember(
            $cacheKey,
            $ttl,
            function () use ($installationId): string {
                $jwt = $this->generateJwt();

                $this->github->connection()->authenticate($jwt, authMethod: 'jwt');

                $response = $this->github->connection()
                    ->apps()
                    ->createInstallationToken($installationId);

                /** @var string $token */
                $token = $response['token'];

                return $token;
            }
        );

        return $cachedToken;
    }

    /**
     * Clear the cached installation token.
     */
    public function clearInstallationToken(int $installationId): void
    {
        Cache::forget('github_installation_token_'.$installationId);
    }

    /**
     * Get the GitHub App installation URL for a workspace.
     *
     * @param  string|null  $state  Optional state parameter for callback
     */
    public function getInstallationUrl(?string $state = null): string
    {
        /** @var string $appName */
        $appName = config('github.app_name');
        $url = sprintf('https://github.com/apps/%s/installations/new', $appName);

        if ($state !== null) {
            $url .= '?state='.urlencode($state);
        }

        return $url;
    }

    /**
     * Get the GitHub App slug/name.
     */
    public function getAppName(): string
    {
        /** @var string $appName */
        $appName = config('github.app_name');

        return $appName;
    }
}
