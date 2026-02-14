<?php

declare(strict_types=1);

namespace App\Services\GitHub\Support;

use Github\Exception\RuntimeException;
use GrahamCampbell\GitHub\GitHubManager;

final readonly class GitHubRepositoryContentOperations
{
    /**
     * Create a new operations instance.
     */
    public function __construct(
        private GitHubApiRequestExecutor $requestExecutor,
        private GitHubApiResponseGuard $responseGuard,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getRepository(int $installationId, string $owner, string $repo): array
    {
        return $this->responseGuard->map($this->requestExecutor->runInstallation(
            $installationId,
            sprintf('getRepository(%s/%s)', $owner, $repo),
            fn (GitHubManager $github): array => $github->connection()->repo()->show($owner, $repo),
        ));
    }

    /**
     * @return array<string, mixed>|string
     */
    public function getFileContents(int $installationId, string $owner, string $repo, string $path, ?string $ref): array|string
    {
        return $this->responseGuard->mapOrString($this->requestExecutor->runInstallation(
            $installationId,
            sprintf('getFileContents(%s/%s/%s)', $owner, $repo, $path),
            fn (GitHubManager $github): array|string => $github->connection()->repo()->contents()->show($owner, $repo, $path, $ref),
        ));
    }

    /**
     * @return array{sha: string, url: string, tree: array<int, array{path: string, mode: string, type: string, sha: string, size?: int}>, truncated: bool}
     */
    public function getRepositoryTree(
        int $installationId,
        string $owner,
        string $repo,
        string $sha,
        bool $recursive = false
    ): array {
        return $this->responseGuard->repositoryTree($this->requestExecutor->runInstallation(
            $installationId,
            sprintf('getRepositoryTree(%s/%s@%s)', $owner, $repo, mb_substr($sha, 0, 7)),
            fn (GitHubManager $github): array => $github->connection()->git()->trees()->show(
                $owner,
                $repo,
                $sha,
                $recursive
            ),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function getReference(int $installationId, string $owner, string $repo, string $ref): array
    {
        return $this->responseGuard->map($this->requestExecutor->runInstallation(
            $installationId,
            sprintf('getReference(%s/%s@%s)', $owner, $repo, $ref),
            fn (GitHubManager $github): array => $github->connection()->git()->references()->show($owner, $repo, $ref),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function createReference(int $installationId, string $owner, string $repo, string $ref, string $sha): array
    {
        return $this->responseGuard->map($this->requestExecutor->runInstallation(
            $installationId,
            sprintf('createReference(%s/%s@%s)', $owner, $repo, $ref),
            fn (GitHubManager $github): array => $github->connection()->git()->references()->create($owner, $repo, [
                'ref' => $ref,
                'sha' => $sha,
            ]),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function createFile(
        int $installationId,
        string $owner,
        string $repo,
        string $path,
        string $content,
        string $message,
        string $branch
    ): array {
        return $this->responseGuard->map($this->requestExecutor->runInstallation(
            $installationId,
            sprintf('createFile(%s/%s/%s)', $owner, $repo, $path),
            fn (GitHubManager $github): array => $github->connection()->repo()->contents()->create(
                $owner,
                $repo,
                $path,
                $content,
                $message,
                $branch
            ),
        ));
    }

    public function fileExists(int $installationId, string $owner, string $repo, string $path, ?string $ref = null): bool
    {
        try {
            $this->getFileContents($installationId, $owner, $repo, $path, $ref);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }
}
