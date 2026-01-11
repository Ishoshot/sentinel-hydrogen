<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Models\Repository;
use App\Models\Run;
use App\Services\GitHub\GitHubApiService;
use App\Services\Reviews\Contracts\PullRequestDataResolver;
use RuntimeException;

final readonly class GitHubPullRequestDataResolver implements PullRequestDataResolver
{
    /**
     * Create a new resolver instance.
     */
    public function __construct(private GitHubApiService $gitHubApiService) {}

    /**
     * Resolve pull request data from the run metadata and GitHub API.
     *
     * @return array{pull_request: array{number: int, title: string, body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, repository_full_name: string, author: array{login: string, avatar_url: string|null}, is_draft: bool, assignees: array<int, array{login: string, avatar_url: string|null}>, reviewers: array<int, array{login: string, avatar_url: string|null}>, labels: array<int, array{name: string, color: string}>}, files: array<int, array{filename: string, additions: int, deletions: int, changes: int}>, metrics: array{files_changed: int, lines_added: int, lines_deleted: int}}
     */
    public function resolve(Repository $repository, Run $run): array
    {
        $metadata = $run->metadata ?? [];

        $fullName = $this->getString($metadata, 'repository_full_name', $repository->full_name ?? '');
        $pullRequestNumber = $this->getInt($metadata, 'pull_request_number', 0);
        $pullRequestTitle = $this->getString($metadata, 'pull_request_title', '');
        $pullRequestBody = $this->getStringOrNull($metadata, 'pull_request_body');
        $baseBranch = $this->getString($metadata, 'base_branch', $repository->default_branch ?? 'main');
        $headBranch = $this->getString($metadata, 'head_branch', '');
        $headSha = $this->getString($metadata, 'head_sha', '');
        $senderLogin = $this->getString($metadata, 'sender_login', '');
        $installationId = $metadata['installation_id'] ?? $repository->installation?->installation_id;

        // New fields with defaults for backwards compatibility
        $author = $this->getAuthor($metadata, $senderLogin);
        $isDraft = $this->getBool($metadata, 'is_draft', false);
        $assignees = $this->getUsers($metadata, 'assignees');
        $reviewers = $this->getUsers($metadata, 'reviewers');
        $labels = $this->getLabels($metadata);

        if ($fullName === '' || ! str_contains($fullName, '/')) {
            throw new RuntimeException('Repository full name missing for review run.');
        }

        if ($pullRequestNumber <= 0) {
            throw new RuntimeException('Pull request number missing for review run.');
        }

        if ($installationId === null) {
            throw new RuntimeException('Installation id missing for review run.');
        }

        [$owner, $repo] = explode('/', $fullName, 2);

        $installationIdInt = is_int($installationId) ? $installationId : (is_numeric($installationId) ? (int) $installationId : 0);

        $files = $this->gitHubApiService->getPullRequestFiles(
            $installationIdInt,
            $owner,
            $repo,
            $pullRequestNumber
        );

        $normalizedFiles = array_map(fn (array $file): array => [
            'filename' => $this->getString($file, 'filename', ''),
            'additions' => $this->getInt($file, 'additions', 0),
            'deletions' => $this->getInt($file, 'deletions', 0),
            'changes' => $this->getInt($file, 'changes', 0),
        ], $files);

        $linesAdded = array_sum(array_column($normalizedFiles, 'additions'));
        $linesDeleted = array_sum(array_column($normalizedFiles, 'deletions'));

        return [
            'pull_request' => [
                'number' => $pullRequestNumber,
                'title' => $pullRequestTitle,
                'body' => $pullRequestBody,
                'base_branch' => $baseBranch,
                'head_branch' => $headBranch,
                'head_sha' => $headSha,
                'sender_login' => $senderLogin,
                'repository_full_name' => $fullName,
                'author' => $author,
                'is_draft' => $isDraft,
                'assignees' => $assignees,
                'reviewers' => $reviewers,
                'labels' => $labels,
            ],
            'files' => $normalizedFiles,
            'metrics' => [
                'files_changed' => count($normalizedFiles),
                'lines_added' => $linesAdded,
                'lines_deleted' => $linesDeleted,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function getString(array $data, string $key, string $default): string
    {
        $value = $data[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function getStringOrNull(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function getInt(array $data, string $key, int $default): int
    {
        $value = $data[$key] ?? $default;

        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : $default);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function getBool(array $data, string $key, bool $default): bool
    {
        $value = $data[$key] ?? $default;

        return is_bool($value) ? $value : $default;
    }

    /**
     * Get author from metadata, falling back to sender_login for backwards compatibility.
     *
     * @param  array<string, mixed>  $metadata
     * @return array{login: string, avatar_url: string|null}
     */
    private function getAuthor(array $metadata, string $fallbackLogin): array
    {
        $author = $metadata['author'] ?? null;

        if (is_array($author) && isset($author['login']) && is_string($author['login'])) {
            return [
                'login' => $author['login'],
                'avatar_url' => isset($author['avatar_url']) && is_string($author['avatar_url']) ? $author['avatar_url'] : null,
            ];
        }

        return [
            'login' => $fallbackLogin,
            'avatar_url' => null,
        ];
    }

    /**
     * Get users array (assignees or reviewers) from metadata.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<int, array{login: string, avatar_url: string|null}>
     */
    private function getUsers(array $metadata, string $key): array
    {
        $users = $metadata[$key] ?? [];

        if (! is_array($users)) {
            return [];
        }

        $result = [];
        foreach ($users as $user) {
            if (is_array($user) && isset($user['login']) && is_string($user['login'])) {
                $result[] = [
                    'login' => $user['login'],
                    'avatar_url' => isset($user['avatar_url']) && is_string($user['avatar_url']) ? $user['avatar_url'] : null,
                ];
            }
        }

        return $result;
    }

    /**
     * Get labels array from metadata.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<int, array{name: string, color: string}>
     */
    private function getLabels(array $metadata): array
    {
        $labels = $metadata['labels'] ?? [];

        if (! is_array($labels)) {
            return [];
        }

        $result = [];
        foreach ($labels as $label) {
            if (is_array($label) && isset($label['name']) && is_string($label['name'])) {
                $result[] = [
                    'name' => $label['name'],
                    'color' => isset($label['color']) && is_string($label['color']) ? $label['color'] : 'cccccc',
                ];
            }
        }

        return $result;
    }
}
