<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\GitHub\Support\GitHubContentDecoder;

/**
 * Fetches individual file contents from GitHub with size enforcement.
 */
final readonly class FileContentFetcher
{
    /**
     * Maximum file size in bytes (skip large files).
     */
    private const int MAX_FILE_SIZE = 50000;

    /**
     * Create a new file content fetcher instance.
     */
    public function __construct(
        private GitHubApiServiceContract $gitHubApiService,
        private GitHubContentDecoder $contentDecoder = new GitHubContentDecoder,
    ) {}

    /**
     * Fetch file content from GitHub, returning null for oversized or undecodable files.
     */
    public function fetch(int $installationId, string $owner, string $repo, string $path, string $ref): ?string
    {
        $response = $this->gitHubApiService->getFileContents(
            $installationId,
            $owner,
            $repo,
            $path,
            $ref
        );

        if (is_string($response)) {
            return mb_strlen($response) <= self::MAX_FILE_SIZE ? $response : null;
        }

        $size = $response['size'] ?? 0;
        if (! is_int($size) || $size > self::MAX_FILE_SIZE) {
            return null;
        }

        $content = $this->contentDecoder->decode($response);

        if ($content === null) {
            return null;
        }

        return mb_strlen($content) <= self::MAX_FILE_SIZE ? $content : null;
    }
}
