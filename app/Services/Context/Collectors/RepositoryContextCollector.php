<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors;

use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextCollector;
use App\Services\GitHub\GitHubApiService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Collects repository context files like README and CONTRIBUTING.
 *
 * Fetches documentation files that help the AI understand project
 * conventions, coding standards, and contribution guidelines.
 */
final readonly class RepositoryContextCollector implements ContextCollector
{
    /**
     * Maximum content length for each file (in characters).
     * ~4000 tokens per file max.
     */
    private const int MAX_CONTENT_LENGTH = 16000;

    /**
     * Files to attempt to fetch in priority order.
     *
     * @var array<string>
     */
    private const array README_FILES = [
        'README.md',
        'readme.md',
        'README.MD',
        'README',
        'README.txt',
    ];

    /**
     * Contributing guide files in priority order.
     *
     * @var array<string>
     */
    private const array CONTRIBUTING_FILES = [
        'CONTRIBUTING.md',
        'contributing.md',
        '.github/CONTRIBUTING.md',
        'docs/CONTRIBUTING.md',
        'CONTRIBUTING',
    ];

    public function __construct(private GitHubApiService $gitHubApiService) {}

    public function name(): string
    {
        return 'repository_context';
    }

    public function priority(): int
    {
        return 50; // Lower priority - supplementary context
    }

    public function shouldCollect(array $params): bool
    {
        return isset($params['repository'], $params['run'])
            && $params['repository'] instanceof Repository
            && $params['run'] instanceof Run;
    }

    public function collect(ContextBag $bag, array $params): void
    {
        /** @var Repository $repository */
        $repository = $params['repository'];

        $repository->loadMissing('installation');
        $installation = $repository->installation;

        if ($installation === null) {
            return;
        }

        $fullName = $repository->full_name ?? '';
        if ($fullName === '' || ! str_contains($fullName, '/')) {
            return;
        }

        [$owner, $repo] = explode('/', $fullName, 2);
        $installationId = $installation->installation_id;

        $context = [];

        // Fetch README
        $readme = $this->fetchFirstAvailable(
            $installationId,
            $owner,
            $repo,
            self::README_FILES
        );

        if ($readme !== null) {
            $context['readme'] = $this->truncateContent($readme, 'README');
        }

        // Fetch CONTRIBUTING guide
        $contributing = $this->fetchFirstAvailable(
            $installationId,
            $owner,
            $repo,
            self::CONTRIBUTING_FILES
        );

        if ($contributing !== null) {
            $context['contributing'] = $this->truncateContent($contributing, 'CONTRIBUTING');
        }

        $bag->repositoryContext = $context;

        Log::info('RepositoryContextCollector: Collected repository context', [
            'repository' => $fullName,
            'has_readme' => isset($context['readme']),
            'has_contributing' => isset($context['contributing']),
        ]);
    }

    /**
     * Fetch the first available file from a list of candidates.
     *
     * @param  array<string>  $files
     */
    private function fetchFirstAvailable(
        int $installationId,
        string $owner,
        string $repo,
        array $files
    ): ?string {
        foreach ($files as $file) {
            try {
                $content = $this->fetchFileContent($installationId, $owner, $repo, $file);
                if ($content !== null && $content !== '') {
                    return $content;
                }
            } catch (Throwable) {
                // File doesn't exist, try next
                continue;
            }
        }

        return null;
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

            // Handle different response formats
            if (is_string($response)) {
                return $response;
            }

            // Response is array - check if content is base64 encoded
            if (isset($response['content']) && is_string($response['content'])) {
                $content = $response['content'];
                $encoding = $response['encoding'] ?? 'base64';

                if ($encoding === 'base64') {
                    // Remove newlines that GitHub adds and decode
                    $decoded = base64_decode(str_replace("\n", '', $content), true);

                    return $decoded !== false ? $decoded : null;
                }

                return $content;
            }

            return null;
        } catch (Throwable $e) {
            Log::debug('RepositoryContextCollector: Failed to fetch file', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Truncate content if it exceeds the maximum length.
     */
    private function truncateContent(string $content, string $type): string
    {
        if (mb_strlen($content) <= self::MAX_CONTENT_LENGTH) {
            return $content;
        }

        $truncated = mb_substr($content, 0, self::MAX_CONTENT_LENGTH);

        // Try to break at a paragraph or line boundary
        $lastParagraph = mb_strrpos($truncated, "\n\n");
        $lastLine = mb_strrpos($truncated, "\n");

        if ($lastParagraph !== false && $lastParagraph > self::MAX_CONTENT_LENGTH * 0.8) {
            $truncated = mb_substr($truncated, 0, $lastParagraph);
        } elseif ($lastLine !== false && $lastLine > self::MAX_CONTENT_LENGTH * 0.9) {
            $truncated = mb_substr($truncated, 0, $lastLine);
        }

        return $truncated."\n\n[{$type} truncated due to length]";
    }
}
