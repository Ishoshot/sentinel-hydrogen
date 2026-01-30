<?php

declare(strict_types=1);

namespace App\Services\Commands;

use App\Models\CommandRun;
use App\Services\Commands\Contracts\PullRequestContextServiceContract;
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
    private const int MAX_DIFF_CHARS = 5000;

    private const int MAX_COMMENTS = 10;

    /**
     * Create a new PullRequestContextService instance.
     */
    public function __construct(
        private GitHubApiServiceContract $githubApi,
    ) {}

    /**
     * Build context string from pull request data.
     */
    public function buildContext(CommandRun $commandRun): ?string
    {
        $prParams = $this->resolvePullRequestParams($commandRun);
        if ($prParams === null) {
            return null;
        }

        try {
            $pr = $this->githubApi->getPullRequest(...$prParams);
            $files = $this->githubApi->getPullRequestFiles(...$prParams);
            $comments = $this->githubApi->getPullRequestComments(...$prParams);

            return $this->formatPRContext($pr, $files, $comments);
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
        $prParams = $this->resolvePullRequestParams($commandRun);
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

    /**
     * Resolve the pull request API parameters from a command run.
     *
     * @return array{0: int, 1: string, 2: string, 3: int}|null
     */
    private function resolvePullRequestParams(CommandRun $commandRun): ?array
    {
        if (! $commandRun->is_pull_request || $commandRun->issue_number === null) {
            return null;
        }

        $repository = $commandRun->repository;
        if ($repository === null) {
            return null;
        }

        $installation = $repository->installation;
        if ($installation === null) {
            return null;
        }

        [$owner, $repo] = explode('/', (string) $repository->full_name);

        return [$installation->installation_id, $owner, $repo, $commandRun->issue_number];
    }

    /**
     * Format the PR context for the agent.
     *
     * @param  array<string, mixed>  $pr
     * @param  array<int, array<string, mixed>>  $files
     * @param  array<int, array<string, mixed>>  $comments
     */
    private function formatPRContext(array $pr, array $files, array $comments): string
    {
        $title = (string) ($pr['title'] ?? 'Untitled');
        $description = (string) ($pr['body'] ?? 'No description provided.');
        $baseBranch = is_array($pr['base'] ?? null) ? (string) ($pr['base']['ref'] ?? 'unknown') : 'unknown';
        $headBranch = is_array($pr['head'] ?? null) ? (string) ($pr['head']['ref'] ?? 'unknown') : 'unknown';
        $additions = (int) ($pr['additions'] ?? 0);
        $deletions = (int) ($pr['deletions'] ?? 0);
        $changedFiles = (int) ($pr['changed_files'] ?? count($files));

        // Format changed files with their patches (diffs)
        $fileChanges = $this->formatFileChanges($files);

        // Format recent comments
        $formattedComments = $this->formatComments($comments);

        $context = <<<CTX
## Pull Request Context

**Title**: {$title}

**Branch**: {$headBranch} → {$baseBranch}

**Stats**: {$changedFiles} files changed, +{$additions} / -{$deletions}

**Description**:
{$description}

### Changed Files

{$fileChanges}

CTX;

        if ($formattedComments !== '') {
            $context .= <<<CTX

### Recent Comments

{$formattedComments}

CTX;
        }

        return $context."---\n";
    }

    /**
     * Format the changed files with their patches.
     *
     * @param  array<int, array<string, mixed>>  $files
     */
    private function formatFileChanges(array $files): string
    {
        if ($files === []) {
            return 'No files changed.';
        }

        $totalDiffSize = 0;
        $formatted = [];

        foreach ($files as $file) {
            $filename = (string) ($file['filename'] ?? 'unknown');
            $status = (string) ($file['status'] ?? 'modified');
            $additions = (int) ($file['additions'] ?? 0);
            $deletions = (int) ($file['deletions'] ?? 0);
            $patch = (string) ($file['patch'] ?? '');

            // Status emoji
            $statusEmoji = match ($status) {
                'added' => '[+]',
                'removed' => '[-]',
                'renamed' => '[→]',
                default => '[M]',
            };

            $fileHeader = sprintf('%s `%s` (+%s / -%s)', $statusEmoji, $filename, $additions, $deletions);

            // Include patch if we have room
            if ($patch !== '' && $totalDiffSize < self::MAX_DIFF_CHARS) {
                $patchToAdd = $patch;
                $remainingChars = self::MAX_DIFF_CHARS - $totalDiffSize;

                if (mb_strlen($patch) > $remainingChars) {
                    $patchToAdd = mb_substr($patch, 0, $remainingChars)."\n... (diff truncated)";
                }

                $totalDiffSize += mb_strlen($patchToAdd);
                $formatted[] = sprintf("%s\n```diff\n%s\n```", $fileHeader, $patchToAdd);
            } else {
                $formatted[] = $fileHeader;
            }
        }

        return implode("\n\n", $formatted);
    }

    /**
     * Format the PR comments.
     *
     * @param  array<int, array<string, mixed>>  $comments
     */
    private function formatComments(array $comments): string
    {
        if ($comments === []) {
            return '';
        }

        // Take only the most recent comments
        $recentComments = array_slice($comments, -self::MAX_COMMENTS);

        $formatted = array_map(function (array $comment): string {
            $user = $comment['user']['login'] ?? 'unknown';
            $body = $comment['body'] ?? '';

            // Truncate long comments
            if (mb_strlen($body) > 300) {
                $body = mb_substr($body, 0, 300).'...';
            }

            return sprintf('**@%s**: %s', $user, $body);
        }, $recentComments);

        return implode("\n\n", $formatted);
    }
}
