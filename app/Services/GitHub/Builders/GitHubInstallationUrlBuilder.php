<?php

declare(strict_types=1);

namespace App\Services\GitHub\Builders;

/**
 * Builds GitHub App installation and configuration URLs.
 */
final readonly class GitHubInstallationUrlBuilder
{
    /**
     * Get the GitHub App installation URL.
     *
     * @param  string|null  $state  Optional state parameter for callback
     */
    public function getInstallationUrl(?string $state = null): string
    {
        $url = sprintf('https://github.com/apps/%s/installations/new', $this->getAppName());

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

    /**
     * Get the URL to configure an existing GitHub App installation.
     *
     * @param  int  $installationId  The GitHub installation ID
     * @param  string  $accountLogin  The GitHub account login (user or org name)
     * @param  bool  $isOrganization  Whether this is an organization installation
     */
    public function getInstallationConfigureUrl(int $installationId, string $accountLogin, bool $isOrganization): string
    {
        if ($isOrganization) {
            return sprintf(
                'https://github.com/organizations/%s/settings/installations/%d',
                urlencode($accountLogin),
                $installationId
            );
        }

        return sprintf('https://github.com/settings/installations/%d', $installationId);
    }
}
