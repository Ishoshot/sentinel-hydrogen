<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\GitHub\Support\GitHubContentDecoder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches the first available repository documentation file from a candidate list.
 */
final readonly class RepositoryDocumentFetcher
{
    /**
     * Maximum content length for each file (in characters).
     */
    private const int MAX_CONTENT_LENGTH = 16000;

    public function __construct(
        private GitHubApiServiceContract $gitHubApiService,
        private GitHubContentDecoder $contentDecoder = new GitHubContentDecoder,
    ) {}

    /**
     * Fetch the first available file from a list of candidates.
     *
     * @param  array<string>  $files
     * @return array{path: string, content: string}|null
     */
    public function fetchFirstAvailable(
        int $installationId,
        string $owner,
        string $repo,
        array $files
    ): ?array {
        foreach ($files as $file) {
            try {
                $content = $this->fetchFileContent($installationId, $owner, $repo, $file);
                if ($content !== null && $content !== '') {
                    return [
                        'path' => $file,
                        'content' => $content,
                    ];
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * Truncate content if it exceeds the maximum length.
     */
    public function truncateContent(string $content, string $type): string
    {
        if (mb_strlen($content) <= self::MAX_CONTENT_LENGTH) {
            return $content;
        }

        $truncated = mb_substr($content, 0, self::MAX_CONTENT_LENGTH);

        $lastParagraph = mb_strrpos($truncated, "\n\n");
        $lastLine = mb_strrpos($truncated, "\n");

        if ($lastParagraph !== false && $lastParagraph > self::MAX_CONTENT_LENGTH * 0.8) {
            $truncated = mb_substr($truncated, 0, $lastParagraph);
        } elseif ($lastLine !== false && $lastLine > self::MAX_CONTENT_LENGTH * 0.9) {
            $truncated = mb_substr($truncated, 0, $lastLine);
        }

        return $truncated."\n\n[{$type} truncated due to length]";
    }

    /**
     * Fetch file content from GitHub.
     */
    private function fetchFileContent(
        int $installationId,
        string $owner,
        string $repo,
        string $path
    ): ?string {
        try {
            $response = $this->gitHubApiService->getFileContents(
                $installationId,
                $owner,
                $repo,
                $path
            );
            $content = $this->contentDecoder->decode($response);

            if ($content !== null) {
                return $content;
            }

            Log::debug('RepositoryContextCollector: Unexpected response format', [
                'path' => $path,
            ]);

            return null;
        } catch (Throwable $throwable) {
            Log::debug('RepositoryContextCollector: Failed to fetch file', [
                'path' => $path,
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }
}
