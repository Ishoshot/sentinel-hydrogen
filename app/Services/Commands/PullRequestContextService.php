<?php

declare(strict_types=1);

namespace App\Services\Commands;

use App\Models\CommandRun;
use App\Services\Commands\Contracts\PullRequestContextServiceContract;
use App\Services\Commands\Support\PullRequestApiParameterResolver;
use App\Services\Commands\Support\PullRequestContextFormatter;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Service for building pull request context for command execution.
 *
 * Fetches PR details, diff, and comments to provide rich context
 * when commands are triggered on pull requests.
 */
final readonly class PullRequestContextService implements PullRequestContextServiceContract
{
    /**
     * Create a new PullRequestContextService instance.
     */
    public function __construct(
        private GitHubApiServiceContract $githubApi,
        private PullRequestApiParameterResolver $pullRequestApiParameterResolver,
        private PullRequestContextFormatter $pullRequestContextFormatter,
    ) {}

    /**
     * Build context string from pull request data.
     */
    public function buildContext(CommandRun $commandRun): ?string
    {
        $prParams = $this->pullRequestApiParameterResolver->resolve($commandRun);
        if ($prParams === null) {
            return null;
        }

        try {
            $pr = $this->githubApi->getPullRequest(...$prParams);
            $files = $this->githubApi->getPullRequestFiles(...$prParams);
            $comments = $this->githubApi->getPullRequestComments(...$prParams);

            return $this->pullRequestContextFormatter->format($pr, $files, $comments);
        } catch (Throwable $throwable) {
            Log::warning('Failed to fetch PR context for command', [
                'command_run_id' => $commandRun->id,
                'repository' => $commandRun->repository?->full_name,
                'pr_number' => $commandRun->issue_number,
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get pull request metadata for storing in CommandRun.
     *
     * @return array{pr_title: string, pr_additions: int, pr_deletions: int, pr_changed_files: int, pr_context_included: bool, base_branch: string, head_branch: string}|null
     */
    public function getMetadata(CommandRun $commandRun): ?array
    {
        $prParams = $this->pullRequestApiParameterResolver->resolve($commandRun);
        if ($prParams === null) {
            return null;
        }

        try {
            $pr = $this->githubApi->getPullRequest(...$prParams);

            $baseBranch = is_array($pr['base'] ?? null) ? (string) ($pr['base']['ref'] ?? '') : '';
            $headBranch = is_array($pr['head'] ?? null) ? (string) ($pr['head']['ref'] ?? '') : '';

            return [
                'pr_title' => (string) ($pr['title'] ?? ''),
                'pr_additions' => (int) ($pr['additions'] ?? 0),
                'pr_deletions' => (int) ($pr['deletions'] ?? 0),
                'pr_changed_files' => (int) ($pr['changed_files'] ?? 0),
                'pr_context_included' => true,
                'base_branch' => $baseBranch,
                'head_branch' => $headBranch,
            ];
        } catch (Throwable $throwable) {
            Log::warning('Failed to fetch PR metadata for command', [
                'command_run_id' => $commandRun->id,
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }
}
