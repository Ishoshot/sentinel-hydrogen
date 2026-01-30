<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Services\Context\ContextBag;
use App\Services\GitHub\GitHubApiService;
use App\Support\PromptRenderer;

/**
 * Builds prompts for the AI review engine.
 */
final readonly class ReviewPromptBuilder
{
    public const string SYSTEM_PROMPT_VERSION = 'review-system@1';

    public const string USER_PROMPT_VERSION = 'review-user@1';

    /**
     * Create a new builder instance.
     */
    public function __construct(
        private GitHubApiService $gitHubApiService,
        private PromptRenderer $renderer,
    ) {}

    /**
     * Build the system prompt for the AI review engine.
     *
     * @param  array<string, mixed>  $policySnapshot
     * @param  array<int, array{path: string, description: string|null, content: string}>  $guidelines
     */
    public function buildSystemPrompt(array $policySnapshot, array $guidelines = []): string
    {
        return $this->renderer->render('prompts.review-system', [
            'policy' => $policySnapshot,
            'guidelines' => $guidelines,
        ]);
    }

    /**
     * Build the user prompt from a ContextBag.
     */
    public function buildUserPromptFromBag(ContextBag $bag): string
    {
        $sensitiveFiles = $bag->metadata['sensitive_files'] ?? [];

        return $this->renderer->render('prompts.review-user', [
            'pull_request' => $bag->pullRequest,
            'files' => $bag->files,
            'metrics' => $bag->metrics,
            'linked_issues' => $bag->linkedIssues,
            'pr_comments' => $bag->prComments,
            'repository_context' => $bag->repositoryContext,
            'review_history' => $bag->reviewHistory,
            'guidelines' => $bag->guidelines,
            'project_context' => $bag->projectContext,
            'sensitive_files' => is_array($sensitiveFiles) ? $sensitiveFiles : [],
        ]);
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

        return array_any($skipPatterns, static fn (string $pattern): bool => preg_match($pattern, $filename) === 1);
    }
}
