<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Contracts;

use App\Models\Repository;

interface CodeIndexingServiceContract
{
    /**
     * Index a repository at a specific commit.
     *
     * @param  Repository  $repository  The repository to index
     * @param  string  $commitSha  The commit SHA to index at
     */
    public function indexRepository(Repository $repository, string $commitSha): void;

    /**
     * Index only changed files (incremental indexing).
     *
     * @param  Repository  $repository  The repository
     * @param  string  $commitSha  The new commit SHA
     * @param  array{added?: array<string>, modified?: array<string>, removed?: array<string>}  $changedFiles
     */
    public function indexChangedFiles(Repository $repository, string $commitSha, array $changedFiles): void;

    /**
     * Index a single file.
     *
     * @param  Repository  $repository  The repository
     * @param  string  $commitSha  The commit SHA
     * @param  string  $filePath  The file path
     * @param  string  $content  The file content
     * @return array{indexed: bool, structure: array<string, mixed>|null}
     */
    public function indexFile(Repository $repository, string $commitSha, string $filePath, string $content): array;

    /**
     * Remove indexed files that no longer exist.
     *
     * @param  Repository  $repository  The repository
     * @param  array<string>  $filePaths  The file paths to remove
     */
    public function removeFiles(Repository $repository, array $filePaths): void;

    /**
     * Check if a file should be indexed based on type and path.
     */
    public function shouldIndexFile(string $filePath): bool;
}
