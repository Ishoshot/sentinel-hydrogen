<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing;

use App\Enums\Queue\Queue;
use App\Jobs\CodeIndexing\IndexCodeBatchJob;
use App\Models\CodeIndex;
use App\Models\Repository;
use App\Services\CodeIndexing\Contracts\CodeIndexingServiceContract;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\Semantic\Contracts\SemanticAnalyzerInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Service for indexing repository code and extracting structure information.
 */
final readonly class CodeIndexingService implements CodeIndexingServiceContract
{
    /** @var array<string> */
    private const array INDEXABLE_EXTENSIONS = [
        // Core languages
        'php', 'js', 'mjs', 'cjs', 'jsx', 'ts', 'tsx', 'py', 'go', 'rs',
        // JVM languages
        'java', 'kt', 'kts', 'scala', 'groovy',
        // .NET
        'cs', 'fs',
        // Dynamic languages
        'rb',
        // Apple ecosystem
        'swift',
        // Systems languages
        'c', 'h', 'cpp', 'cc', 'cxx', 'hpp',
        // Frontend frameworks
        'vue', 'svelte',
        // Functional languages
        'ex', 'exs', 'hs',
        // Data & config (for understanding structure)
        'sql', 'yaml', 'yml', 'json',
        // Shell
        'sh', 'bash',
        // Markup (for documentation)
        'md', 'mdx',
    ];

    /** @var array<string> */
    private const array EXCLUDED_PATHS = [
        'vendor/',
        'node_modules/',
        '.git/',
        'dist/',
        'build/',
        'storage/',
        'public/build/',
        'public/vendor/',
        '.idea/',
        '.vscode/',
        '__pycache__/',
        '.pytest_cache/',
        'coverage/',
        '.nyc_output/',
    ];

    private const int MAX_FILE_SIZE = 512_000; // 500KB

    private const int BATCH_SIZE = 50;

    /**
     * Create a new CodeIndexingService instance.
     */
    public function __construct(
        private GitHubApiServiceContract $githubApi,
        private SemanticAnalyzerInterface $semanticAnalyzer,
    ) {}

    /**
     * Index a repository at a specific commit.
     */
    public function indexRepository(Repository $repository, string $commitSha): void
    {
        Log::info('Starting full repository index', [
            'repository_id' => $repository->id,
            'commit_sha' => $commitSha,
        ]);

        $installation = $repository->installation;
        if ($installation === null) {
            Log::warning('Cannot index repository without installation', [
                'repository_id' => $repository->id,
            ]);

            return;
        }

        // Get repository tree (all files)
        $tree = $this->getRepositoryTree($installation->installation_id, $repository->owner, $repository->name, $commitSha);

        // Filter to indexable files
        $indexableFiles = $this->filterIndexableFiles($tree);

        Log::info('Found indexable files', [
            'repository_id' => $repository->id,
            'total_files' => count($tree),
            'indexable_files' => count($indexableFiles),
        ]);

        // Dispatch batch jobs
        $this->dispatchBatchJobs($repository, $commitSha, $indexableFiles);
    }

    /**
     * Index only changed files (incremental indexing).
     *
     * @param  array{added: array<string>, modified: array<string>, removed: array<string>}  $changedFiles
     */
    public function indexChangedFiles(Repository $repository, string $commitSha, array $changedFiles): void
    {
        $added = $changedFiles['added'];
        $modified = $changedFiles['modified'];
        $removed = $changedFiles['removed'];

        Log::info('Starting incremental index', [
            'repository_id' => $repository->id,
            'commit_sha' => $commitSha,
            'added' => count($added),
            'modified' => count($modified),
            'removed' => count($removed),
        ]);

        // Remove deleted files from index
        if ($removed !== []) {
            $this->removeFiles($repository, $removed);
        }

        // Get files to index (added + modified)
        $filesToIndex = array_unique(array_merge($added, $modified));

        // Filter to indexable files
        $indexableFiles = array_filter(
            array_map(fn (string $path): array => ['path' => $path, 'type' => 'blob'], $filesToIndex),
            fn (array $file): bool => $this->shouldIndexFile($file['path'])
        );

        if ($indexableFiles === []) {
            Log::debug('No indexable files in change set', [
                'repository_id' => $repository->id,
            ]);

            return;
        }

        // If too many files changed, fall back to full index
        if (count($indexableFiles) > 500) {
            Log::info('Large change set detected, triggering full reindex', [
                'repository_id' => $repository->id,
                'changed_files' => count($indexableFiles),
            ]);

            $this->indexRepository($repository, $commitSha);

            return;
        }

        // Dispatch batch jobs
        $this->dispatchBatchJobs($repository, $commitSha, array_values($indexableFiles));
    }

    /**
     * Index a single file.
     *
     * @return array{indexed: bool, structure: array<string, mixed>|null}
     */
    public function indexFile(Repository $repository, string $commitSha, string $filePath, string $content): array
    {
        if (! $this->shouldIndexFile($filePath)) {
            return ['indexed' => false, 'structure' => null];
        }

        $fileType = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'txt';

        // Analyze structure using SemanticAnalyzer
        $structure = $this->semanticAnalyzer->analyzeFile($content, $filePath);

        // Upsert the code index
        CodeIndex::updateOrCreate(
            [
                'repository_id' => $repository->id,
                'file_path' => $filePath,
            ],
            [
                'commit_sha' => $commitSha,
                'file_type' => $fileType,
                'content' => $content,
                'structure' => $structure,
                'metadata' => [
                    'lines' => mb_substr_count($content, "\n") + 1,
                    'size' => mb_strlen($content),
                ],
                'indexed_at' => now(),
            ]
        );

        return ['indexed' => true, 'structure' => $structure];
    }

    /**
     * Remove indexed files that no longer exist.
     *
     * @param  array<string>  $filePaths
     */
    public function removeFiles(Repository $repository, array $filePaths): void
    {
        if ($filePaths === []) {
            return;
        }

        $deleted = CodeIndex::where('repository_id', $repository->id)
            ->whereIn('file_path', $filePaths)
            ->delete();

        Log::info('Removed files from index', [
            'repository_id' => $repository->id,
            'requested' => count($filePaths),
            'deleted' => $deleted,
        ]);
    }

    /**
     * Check if a file should be indexed based on type and path.
     */
    public function shouldIndexFile(string $filePath): bool
    {
        // Check excluded paths
        foreach (self::EXCLUDED_PATHS as $excluded) {
            if (str_starts_with($filePath, $excluded) || str_contains($filePath, '/'.$excluded)) {
                return false;
            }
        }

        // Check extension
        $extension = mb_strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return in_array($extension, self::INDEXABLE_EXTENSIONS, true);
    }

    /**
     * Get the repository tree from GitHub.
     *
     * @return array<int, array{path: string, type: string, size?: int}>
     */
    private function getRepositoryTree(int $installationId, string $owner, string $repo, string $sha): array
    {
        try {
            // Get the tree recursively
            $result = $this->githubApi->getRepositoryTree($installationId, $owner, $repo, $sha, true);

            /** @var array<int, array{path: string, type: string, size?: int}> $tree */
            $tree = $result['tree'];

            return $tree;
        } catch (Throwable $throwable) {
            Log::error('Failed to get repository tree', [
                'owner' => $owner,
                'repo' => $repo,
                'sha' => $sha,
                'error' => $throwable->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Filter tree items to only include indexable files.
     *
     * @param  array<int, array{path: string, type: string, size?: int}>  $tree
     * @return array<int, array{path: string, type: string, size?: int}>
     */
    private function filterIndexableFiles(array $tree): array
    {
        return array_filter($tree, function (array $item): bool {
            // Only process blobs (files, not directories)
            if ($item['type'] !== 'blob') {
                return false;
            }

            // Check file size if available
            if (isset($item['size']) && $item['size'] > self::MAX_FILE_SIZE) {
                return false;
            }

            return $this->shouldIndexFile($item['path']);
        });
    }

    /**
     * Dispatch batch indexing jobs.
     *
     * @param  array<int, array{path: string, type: string, size?: int}>  $files
     */
    private function dispatchBatchJobs(Repository $repository, string $commitSha, array $files): void
    {
        $batches = array_chunk($files, self::BATCH_SIZE);

        foreach ($batches as $batch) {
            IndexCodeBatchJob::dispatch($repository, $commitSha, $batch)
                ->onQueue(Queue::CodeIndexing->value);
        }

        Log::info('Dispatched indexing batch jobs', [
            'repository_id' => $repository->id,
            'total_files' => count($files),
            'batches' => count($batches),
        ]);
    }
}
