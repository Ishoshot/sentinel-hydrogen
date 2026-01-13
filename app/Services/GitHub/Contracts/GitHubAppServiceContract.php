<?php

declare(strict_types=1);

namespace App\Services\GitHub\Contracts;

/**
 * Contract for GitHub App authentication services.
 */
interface GitHubAppServiceContract
{
    /**
     * Generate a JWT for GitHub App authentication.
     *
     * @return string The JWT token
     */
    public function generateJwt(): string;

    /**
     * Get an installation access token (cached for 50 minutes).
     *
     * @param  int  $installationId  The GitHub App installation ID
     * @return string The installation access token
     */
    public function getInstallationToken(int $installationId): string;

    /**
     * Clear the cached installation token.
     */
    public function clearInstallationToken(int $installationId): void;

    /**
     * Get the GitHub App installation URL for a workspace.
     *
     * @param  string|null  $state  Optional state parameter for callback
     */
    public function getInstallationUrl(?string $state = null): string;

    /**
     * Get the GitHub App slug/name.
     */
    public function getAppName(): string;
}
