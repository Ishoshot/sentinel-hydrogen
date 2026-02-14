<?php

declare(strict_types=1);

namespace App\Services\GitHub;

use App\Services\GitHub\Contracts\GitHubAppServiceContract;
use App\Services\GitHub\Support\GitHubInstallationUrlBuilder;
use App\Services\GitHub\Support\GitHubJwtGenerator;
use GrahamCampbell\GitHub\GitHubManager;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

final readonly class GitHubAppService implements GitHubAppServiceContract
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private GitHubManager $github,
        private GitHubJwtGenerator $jwtGenerator = new GitHubJwtGenerator,
        private GitHubInstallationUrlBuilder $urlBuilder = new GitHubInstallationUrlBuilder,
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
        return $this->jwtGenerator->generate();
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
        return $this->urlBuilder->getInstallationUrl($state);
    }

    /**
     * Get the GitHub App slug/name.
     */
    public function getAppName(): string
    {
        return $this->urlBuilder->getAppName();
    }

    /**
     * Get the URL to configure an existing GitHub App installation.
     *
     * @param  int  $installationId  The GitHub installation ID
     * @param  string  $accountLogin  The GitHub account login (user or org name)
     * @param  bool  $isOrganization  Whether this is an organization installation
     */
    public function getInstallationConfigureUrl(int $installationId, string $accountLogin, bool $isOrganization): string
    {
        return $this->urlBuilder->getInstallationConfigureUrl($installationId, $accountLogin, $isOrganization);
    }
}
