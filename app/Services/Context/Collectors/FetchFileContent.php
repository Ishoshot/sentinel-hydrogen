<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors;

use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\GitHub\Parsers\GitHubContentParser;

/**
 * Fetches individual file contents from GitHub with size enforcement.
 */
final readonly class FetchFileContent
{
    /**
     * Create a new file content fetcher instance.
     */
    public function __construct(
        private GitHubApiServiceContract $gitHubApiService,
        private GitHubContentParser $contentDecoder = new GitHubContentParser,
    ) {}

    /**
     * Fetch file content from GitHub, returning null for oversized or undecodable files.
     */
    public function fetch(int $installationId, string $owner, string $repo, string $path, string $ref, int $maxFileSize): ?string
    {
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

        $content = $this->contentDecoder->decode($response);

        if ($content === null) {
            return null;
        }

        return mb_strlen($content) <= $maxFileSize ? $content : null;
    }
}
