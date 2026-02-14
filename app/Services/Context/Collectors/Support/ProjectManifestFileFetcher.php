<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\GitHub\Support\GitHubContentDecoder;
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
        private GitHubContentDecoder $contentDecoder = new GitHubContentDecoder,
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
