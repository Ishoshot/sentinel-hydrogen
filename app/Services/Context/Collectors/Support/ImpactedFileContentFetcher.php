<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

use App\Services\GitHub\Contracts\GitHubApiServiceContract;

/**
 * Fetches impacted file content from GitHub with size and encoding checks.
 */
final readonly class ImpactedFileContentFetcher
{
    /**
     * Create a new fetcher instance.
     */
    public function __construct(
        private GitHubApiServiceContract $gitHubApiService,
    ) {}

    /**
     * Fetch and decode file content for a specific reference.
     */
    public function fetch(
        int $installationId,
        string $owner,
        string $repo,
        string $path,
        ?string $ref,
        int $maxFileSize
    ): ?string {
        $response = $this->gitHubApiService->getFileContents(
            $installationId,
            $owner,
            $repo,
            $path,
            $ref
        );

        if (is_string($response)) {
            return mb_strlen($response) <= $maxFileSize ? $response : null;
        }

        $size = $response['size'] ?? 0;
        if (! is_int($size) || $size > $maxFileSize) {
            return null;
        }

        $content = $response['content'] ?? null;
        $encoding = $response['encoding'] ?? 'base64';

        if (! is_string($content)) {
            return null;
        }

        if ($encoding === 'base64') {
            $decoded = base64_decode($content, true);

            return $decoded !== false ? $decoded : null;
        }

        return $content;
    }
}
