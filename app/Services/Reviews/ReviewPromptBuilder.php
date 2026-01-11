<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Services\Context\ContextBag;
use App\Services\GitHub\GitHubApiService;

/**
 * Builds prompts for the AI review engine.
 */
final readonly class ReviewPromptBuilder
{
    /**
     * Create a new builder instance.
     */
    public function __construct(private GitHubApiService $gitHubApiService) {}

    /**
     * Build the system prompt for the AI review engine.
     *
     * @param  array<string, mixed>  $policySnapshot
     */
    public function buildSystemPrompt(array $policySnapshot): string
    {
        return view('prompts.review-system', [
            'policy' => $policySnapshot,
        ])->render();
    }

    /**
     * Build the user prompt from a ContextBag.
     */
    public function buildUserPromptFromBag(ContextBag $bag): string
    {
        $sensitiveFiles = $bag->metadata['sensitive_files'] ?? [];

        return view('prompts.review-user', [
            'pull_request' => $bag->pullRequest,
            'files' => $bag->files,
            'metrics' => $bag->metrics,
            'linked_issues' => $bag->linkedIssues,
            'pr_comments' => $bag->prComments,
            'repository_context' => $bag->repositoryContext,
            'review_history' => $bag->reviewHistory,
            'guidelines' => $bag->guidelines,
            'sensitive_files' => is_array($sensitiveFiles) ? $sensitiveFiles : [],
        ])->render();
    }

    /**
     * Build the user prompt for the AI review engine.
     *
     * @deprecated Use buildUserPromptFromBag() with ContextBag instead
     *
     * @param  array{pull_request: array{number: int, title: string, body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, repository_full_name: string}, files: array<int, array{filename: string, additions: int, deletions: int, changes: int}>, metrics: array{files_changed: int, lines_added: int, lines_deleted: int}}  $context
     */
    public function buildUserPrompt(array $context): string
    {
        return view('prompts.review-user', [
            'pull_request' => $context['pull_request'],
            'files' => $context['files'],
            'metrics' => $context['metrics'],
            'linked_issues' => [],
            'pr_comments' => [],
            'repository_context' => [],
            'review_history' => [],
        ])->render();
    }

    /**
     * Fetch file contents for changed files in the pull request.
     *
     * @param  array<int, array{filename: string, additions: int, deletions: int, changes: int}>  $files
     * @return array<string, string>
     */
    public function fetchFileContents(
        int $installationId,
        string $owner,
        string $repo,
        string $ref,
        array $files
    ): array {
        $contents = [];

        foreach ($files as $file) {
            $filename = $file['filename'];

            if ($this->shouldSkipFile($filename)) {
                continue;
            }

            $content = $this->gitHubApiService->getFileContents(
                $installationId,
                $owner,
                $repo,
                $filename,
                $ref
            );

            if (is_string($content)) {
                $contents[$filename] = $content;
            }
        }

        return $contents;
    }

    /**
     * Determine if a file should be skipped based on filename patterns.
     */
    private function shouldSkipFile(string $filename): bool
    {
        $skipPatterns = [
            '/^vendor\//',
            '/^node_modules\//',
            '/\.lock$/',
            '/\.min\.(js|css)$/',
            '/\.map$/',
            '/^\./',
        ];

        foreach ($skipPatterns as $pattern) {
            if (preg_match($pattern, $filename) === 1) {
                return true;
            }
        }

        return false;
    }
}
