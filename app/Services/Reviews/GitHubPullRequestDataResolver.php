<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Models\Repository;
use App\Models\Run;
use App\Services\GitHub\GitHubApiService;
use App\Services\Logging\LogContext;
use App\Services\Reviews\Contracts\PullRequestDataResolver;
use App\Support\MetadataExtractor;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Resolves pull request data from GitHub for code reviews.
 */
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
        $metadata = MetadataExtractor::from($run->metadata ?? []);

        $fullName = $metadata->string('repository_full_name', $repository->full_name ?? '');
        $pullRequestNumber = $metadata->int('pull_request_number');
        $installationId = ($run->metadata ?? [])['installation_id'] ?? $repository->installation?->installation_id;

        $ctx = LogContext::merge(LogContext::fromRun($run), ['pr_number' => $pullRequestNumber]);

        if ($fullName === '' || ! str_contains($fullName, '/')) {
            Log::error('Repository full name missing for review run', $ctx);

            throw new RuntimeException('Repository full name missing for review run.');
        }

        if ($pullRequestNumber <= 0) {
            Log::error('Pull request number missing for review run', $ctx);

            throw new RuntimeException('Pull request number missing for review run.');
        }

        if ($installationId === null) {
            Log::error('Installation ID missing for review run', $ctx);

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

        $normalizedFiles = array_map(function (array $file): array {
            $extractor = MetadataExtractor::from($file);

            return [
                'filename' => $extractor->string('filename'),
                'additions' => $extractor->int('additions'),
                'deletions' => $extractor->int('deletions'),
                'changes' => $extractor->int('changes'),
            ];
        }, $files);

        return [
            'pull_request' => [
                'number' => $pullRequestNumber,
                'title' => $metadata->string('pull_request_title'),
                'body' => $metadata->stringOrNull('pull_request_body'),
                'base_branch' => $metadata->string('base_branch', $repository->default_branch ?? 'main'),
                'head_branch' => $metadata->string('head_branch'),
                'head_sha' => $metadata->string('head_sha'),
                'sender_login' => $metadata->string('sender_login'),
                'repository_full_name' => $fullName,
                'author' => $metadata->author(),
                'is_draft' => $metadata->bool('is_draft'),
                'assignees' => $metadata->users('assignees'),
                'reviewers' => $metadata->users('reviewers'),
                'labels' => $metadata->labels(),
            ],
            'files' => $normalizedFiles,
            'metrics' => [
                'files_changed' => count($normalizedFiles),
                'lines_added' => array_sum(array_column($normalizedFiles, 'additions')),
                'lines_deleted' => array_sum(array_column($normalizedFiles, 'deletions')),
            ],
        ];
    }
}
