<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors;

use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\GitHub\Parsers\GitHubContentParser;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches, validates, and truncates guideline files from GitHub.
 */
final readonly class FetchGuidelineContent
{
    /**
     * Maximum content length per file (in bytes).
     */
    private const int MAX_FILE_SIZE = 51200; // 50KB

    /**
     * Allowed file extensions for guidelines.
     *
     * @var array<string>
     */
    private const array ALLOWED_EXTENSIONS = ['md', 'mdx', 'blade.php'];

    /**
     * Create a new FetchGuidelineContent instance.
     */
    public function __construct(
        private GitHubApiServiceContract $gitHubApiService,
        private GitHubContentParser $contentDecoder = new GitHubContentParser,
    ) {}

    /**
     * Check if a file path has an allowed extension.
     */
    public function isAllowedFileType(string $path): bool
    {
        $lowerPath = mb_strtolower($path);

        return array_any(self::ALLOWED_EXTENSIONS, static fn (string $extension): bool => str_ends_with($lowerPath, '.'.$extension));
    }

    /**
     * Fetch a guideline file from GitHub.
     */
    public function fetch(int $installationId, string $owner, string $repo, string $path): ?string
    {
        try {
            $response = $this->gitHubApiService->getFileContents(
                $installationId,
                $owner,
                $repo,
                $path
            );

            $content = $this->contentDecoder->decode($response);

            if ($content === null) {
                Log::debug('GuidelinesCollector: Unexpected response format', [
                    'path' => $path,
                ]);

                return null;
            }

            if (mb_strlen($content, '8bit') > self::MAX_FILE_SIZE) {
                Log::info('GuidelinesCollector: Truncating oversized guideline', [
                    'path' => $path,
                    'original_size' => mb_strlen($content, '8bit'),
                    'max_size' => self::MAX_FILE_SIZE,
                ]);

                return $this->truncateContent($content, $path);
            }

            return $content;
        } catch (Throwable $throwable) {
            Log::debug('GuidelinesCollector: Failed to fetch guideline', [
                'path' => $path,
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Truncate content if it exceeds the maximum size.
     */
    private function truncateContent(string $content, string $path): string
    {
        $charLimit = (int) (self::MAX_FILE_SIZE * 0.9);
        $truncated = mb_substr($content, 0, $charLimit);

        $lastParagraph = mb_strrpos($truncated, "\n\n");
        $lastLine = mb_strrpos($truncated, "\n");

        if ($lastParagraph !== false && $lastParagraph > $charLimit * 0.8) {
            $truncated = mb_substr($truncated, 0, $lastParagraph);
        } elseif ($lastLine !== false && $lastLine > $charLimit * 0.9) {
            $truncated = mb_substr($truncated, 0, $lastLine);
        }

        $filename = basename($path);

        return $truncated."\n\n[{$filename} truncated due to size limit]";
    }
}
