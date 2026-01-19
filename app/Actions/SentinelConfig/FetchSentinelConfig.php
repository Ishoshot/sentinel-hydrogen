<?php

declare(strict_types=1);

namespace App\Actions\SentinelConfig;

use App\Actions\SentinelConfig\Contracts\FetchesSentinelConfig;
use App\Models\Repository;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Support\RepositoryNameParser;
use Github\Exception\RuntimeException;
use Illuminate\Support\Facades\Log;

/**
 * Fetches .sentinel/config.yaml from a repository via the GitHub API.
 */
final readonly class FetchSentinelConfig implements FetchesSentinelConfig
{
    private const string CONFIG_PATH = '.sentinel/config.yaml';

    /**
     * Create a new action instance.
     */
    public function __construct(
        private GitHubApiServiceContract $github,
    ) {}

    /**
     * Fetch .sentinel/config.yaml from a repository.
     *
     * @param  Repository  $repository  The repository to fetch the config from
     * @param  string|null  $ref  The branch/ref to fetch from (defaults to repository's default branch)
     * @return array{found: bool, content: ?string, sha: ?string, error: ?string}
     */
    public function handle(Repository $repository, ?string $ref = null): array
    {
        $installation = $repository->installation;

        if ($installation === null) {
            return [
                'found' => false,
                'content' => null,
                'sha' => null,
                'error' => 'Repository has no installation',
            ];
        }

        $parsed = RepositoryNameParser::parse($repository->full_name);
        if ($parsed === null) {
            return [
                'found' => false,
                'content' => null,
                'sha' => null,
                'error' => 'Invalid repository full_name format',
            ];
        }
        ['owner' => $owner, 'repo' => $repo] = $parsed;

        // Use provided ref or fall back to repository's default branch
        $branch = $ref ?? $repository->default_branch;

        try {
            $response = $this->github->getFileContents(
                $installation->installation_id,
                $owner,
                $repo,
                self::CONFIG_PATH,
                $branch
            );

            // Handle the response - it could be an array with content or direct string
            if (is_array($response)) {
                $responseContent = $response['content'] ?? null;
                $responseEncoding = $response['encoding'] ?? null;
                $responseSha = $response['sha'] ?? null;

                if (is_string($responseContent) && is_string($responseEncoding)) {
                    $content = $responseEncoding === 'base64'
                        ? base64_decode($responseContent, true)
                        : $responseContent;

                    $sha = is_string($responseSha) ? $responseSha : null;

                    if ($content === false) {
                        return [
                            'found' => true,
                            'content' => null,
                            'sha' => $sha,
                            'error' => 'Failed to decode base64 content',
                        ];
                    }

                    return [
                        'found' => true,
                        'content' => $content,
                        'sha' => $sha,
                        'error' => null,
                    ];
                }

                return [
                    'found' => true,
                    'content' => null,
                    'sha' => null,
                    'error' => 'Unexpected response format from GitHub API',
                ];
            }

            // Direct string content (unlikely but handle it)
            return [
                'found' => true,
                'content' => $response,
                'sha' => null,
                'error' => null,
            ];
        } catch (RuntimeException $runtimeException) {
            // 404 means file doesn't exist - this is not an error
            if ($runtimeException->getCode() === 404) {
                return [
                    'found' => false,
                    'content' => null,
                    'sha' => null,
                    'error' => null,
                ];
            }

            Log::warning('Failed to fetch sentinel config', [
                'repository' => $repository->full_name,
                'error' => $runtimeException->getMessage(),
                'code' => $runtimeException->getCode(),
            ]);

            return [
                'found' => false,
                'content' => null,
                'sha' => null,
                'error' => sprintf('GitHub API error: %s', $runtimeException->getMessage()),
            ];
        }
    }
}
