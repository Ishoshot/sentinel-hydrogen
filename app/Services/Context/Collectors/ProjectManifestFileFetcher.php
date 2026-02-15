<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors;

use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\GitHub\Parsers\GitHubContentParser;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches and decodes project manifest files from GitHub.
 */
final readonly class ProjectManifestFileFetcher
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private GitHubApiServiceContract $gitHubApiService,
        private GitHubContentParser $contentDecoder = new GitHubContentParser,
    ) {}

    /**
     * Fetch and decode file content.
     */
    public function fetch(int $installationId, string $owner, string $repo, string $path): ?string
    {
        try {
            $response = $this->gitHubApiService->getFileContents($installationId, $owner, $repo, $path);

            return $this->contentDecoder->decode($response);
        } catch (Throwable $throwable) {
            Log::debug('ProjectContextCollector: Failed to fetch file', [
                'path' => $path,
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }
}
