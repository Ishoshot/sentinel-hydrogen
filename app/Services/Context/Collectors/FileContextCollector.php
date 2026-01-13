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
 * Collects full file contents for touched files in the PR.
 *
 * Fetches the complete file content (not just the diff) to provide
 * the AI with surrounding context for better understanding of changes.
 */
final readonly class FileContextCollector implements ContextCollector
{
    /**
     * Maximum number of files to fetch full content for.
     */
    private const int MAX_FILES = 10;

    /**
     * Maximum file size in bytes (skip large files).
     */
    private const int MAX_FILE_SIZE = 50000;

    /**
     * File extensions to fetch (code files only).
     */
    private const array ALLOWED_EXTENSIONS = [
        'php', 'js', 'ts', 'jsx', 'tsx', 'vue', 'svelte',
        'py', 'rb', 'go', 'rs', 'java', 'kt', 'scala',
        'cs', 'cpp', 'c', 'h', 'hpp',
        'swift', 'dart', 'ex', 'exs',
        'yaml', 'yml', 'json', 'xml', 'toml',
        'sql', 'graphql', 'gql',
        'sh', 'bash', 'zsh',
        'md', 'mdx', 'txt',
    ];

    /**
     * Create a new FileContextCollector instance.
     */
    public function __construct(private GitHubApiService $gitHubApiService) {}

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'file_context';
    }

    /**
     * {@inheritdoc}
     */
    public function priority(): int
    {
        return 85;
    }

    /**
     * {@inheritdoc}
     */
    public function shouldCollect(array $params): bool
    {
        return isset($params['repository'], $params['run'])
            && $params['repository'] instanceof Repository
            && $params['run'] instanceof Run;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(ContextBag $bag, array $params): void
    {
        /** @var Repository $repository */
        $repository = $params['repository'];

        /** @var Run $run */
        $run = $params['run'];

        $metadata = $run->metadata ?? [];
        $repository->loadMissing('installation');
        $installation = $repository->installation;

        if ($installation === null) {
            return;
        }

        $fullName = $repository->full_name ?? '';
        if ($fullName === '' || ! str_contains((string) $fullName, '/')) {
            return;
        }

        [$owner, $repo] = explode('/', (string) $fullName, 2);
        $installationId = $installation->installation_id;
        $headSha = is_string($metadata['head_sha'] ?? null) ? $metadata['head_sha'] : null;

        if ($headSha === null) {
            Log::debug('FileContextCollector: No head SHA available', [
                'repository_id' => $repository->id,
            ]);

            return;
        }

        $filesToFetch = $this->selectFilesToFetch($bag->files);

        if ($filesToFetch === []) {
            Log::debug('FileContextCollector: No suitable files to fetch', [
                'repository_id' => $repository->id,
                'total_files' => count($bag->files),
            ]);

            return;
        }

        $fileContents = [];
        $fetchedCount = 0;

        foreach ($filesToFetch as $file) {
            $filename = $file['filename'];

            try {
                $content = $this->fetchFileContent(
                    $installationId,
                    $owner,
                    $repo,
                    $filename,
                    $headSha
                );

                if ($content !== null) {
                    $fileContents[$filename] = $content;
                    $fetchedCount++;
                }
            } catch (Throwable $e) {
                Log::debug('FileContextCollector: Failed to fetch file', [
                    'file' => $filename,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $bag->fileContents = $fileContents;

        Log::info('FileContextCollector: Collected file contents', [
            'repository' => $fullName,
            'files_fetched' => $fetchedCount,
            'files_requested' => count($filesToFetch),
        ]);
    }

    /**
     * Select which files to fetch full content for.
     *
     * Prioritizes modified files with code changes, skips deleted files
     * and files that are too large or have unsupported extensions.
     *
     * @param  array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>  $files
     * @return array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>
     */
    private function selectFilesToFetch(array $files): array
    {
        $candidates = [];

        foreach ($files as $file) {
            if ($file['status'] === 'removed') {
                continue;
            }

            $extension = mb_strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
            if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                continue;
            }

            $candidates[] = $file;
        }

        usort($candidates, static function (array $a, array $b): int {
            return $b['changes'] <=> $a['changes'];
        });

        return array_slice($candidates, 0, self::MAX_FILES);
    }

    /**
     * Fetch file content from GitHub.
     */
    private function fetchFileContent(
        int $installationId,
        string $owner,
        string $repo,
        string $path,
        string $ref
    ): ?string {
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

        if (! is_array($response)) {
            return null;
        }

        $size = $response['size'] ?? 0;
        if (! is_int($size) || $size > self::MAX_FILE_SIZE) {
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
