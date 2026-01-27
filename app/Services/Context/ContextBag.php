<?php

declare(strict_types=1);

namespace App\Services\Context;

/**
 * Data transfer object holding all context data for a review.
 */
final class ContextBag
{
    /**
     * Approximate tokens per character for estimation.
     */
    private const float TOKENS_PER_CHAR = 0.25;

    /**
     * Create a new context bag instance.
     *
     * @param  array<string, mixed>  $pullRequest
     * @param  array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>  $files
     * @param  array{files_changed?: int, lines_added?: int, lines_deleted?: int}  $metrics
     * @param  array<int, array{number: int, title: string, body: string|null, state: string, labels: array<int, string>, comments: array<int, array{author: string, body: string}>}>  $linkedIssues
     * @param  array<int, array{author: string, body: string, created_at: string}>  $prComments
     * @param  array{readme?: string|null, contributing?: string|null}  $repositoryContext
     * @param  array<int, array{run_id: int, summary: string, findings_count: int, severity_breakdown: array<string, int>, key_findings: array<int, array{severity: string, category: string, title: string, file_path: string|null, line_start: int|null, fingerprint: string}>, created_at: string}>  $reviewHistory
     * @param  array<int, array{path: string, description: string|null, content: string}>  $guidelines
     * @param  array<string, string>  $fileContents  Full file contents for touched files (path => content)
     * @param  array<string, array<string, mixed>>  $semantics  Semantic analysis data (path => analysis)
     * @param  array{languages?: array<string>, runtime?: array{name: string, version: string}|null, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}  $projectContext  Project dependencies and versions
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public array $pullRequest = [],
        public array $files = [],
        public array $metrics = [],
        public array $linkedIssues = [],
        public array $prComments = [],
        public array $repositoryContext = [],
        public array $reviewHistory = [],
        public array $guidelines = [],
        public array $fileContents = [],
        public array $semantics = [],
        public array $projectContext = [],
        public array $metadata = [],
    ) {}

    /**
     * Estimate the total token count for this context.
     */
    public function estimateTokens(): int
    {
        $totalChars = 0;

        // Pull request metadata
        $totalChars += mb_strlen(json_encode($this->pullRequest) ?: '');

        // Files with patches (most significant)
        foreach ($this->files as $file) {
            $totalChars += mb_strlen($file['filename']);
            $totalChars += mb_strlen($file['patch'] ?? '');
        }

        // Metrics
        $totalChars += mb_strlen(json_encode($this->metrics) ?: '');

        // Linked issues
        foreach ($this->linkedIssues as $issue) {
            $totalChars += mb_strlen($issue['title']);
            $totalChars += mb_strlen($issue['body'] ?? '');
            foreach ($issue['comments'] as $comment) {
                $totalChars += mb_strlen($comment['body']);
            }
        }

        // PR comments
        foreach ($this->prComments as $comment) {
            $totalChars += mb_strlen($comment['body']);
        }

        // Repository context
        $totalChars += mb_strlen($this->repositoryContext['readme'] ?? '');
        $totalChars += mb_strlen($this->repositoryContext['contributing'] ?? '');

        // Review history
        foreach ($this->reviewHistory as $review) {
            $totalChars += mb_strlen($review['summary']);
        }

        // Guidelines
        foreach ($this->guidelines as $guideline) {
            $totalChars += mb_strlen($guideline['content']);
        }

        // File contents
        foreach ($this->fileContents as $content) {
            $totalChars += mb_strlen($content);
        }

        // Semantic analysis data
        foreach ($this->semantics as $data) {
            $totalChars += mb_strlen(json_encode($data) ?: '');
        }

        // Project context
        $totalChars += mb_strlen(json_encode($this->projectContext) ?: '');

        return (int) ceil($totalChars * self::TOKENS_PER_CHAR);
    }

    /**
     * Get the total number of files with patches.
     */
    public function getFilesWithPatchCount(): int
    {
        return count(array_filter($this->files, fn (array $file): bool => isset($file['patch'])));
    }

    /**
     * Recalculate metrics based on current files.
     */
    public function recalculateMetrics(): void
    {
        $this->metrics = [
            'files_changed' => count($this->files),
            'lines_added' => array_sum(array_column($this->files, 'additions')),
            'lines_deleted' => array_sum(array_column($this->files, 'deletions')),
        ];
    }

    /**
     * Convert the context bag to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'pull_request' => $this->pullRequest,
            'files' => $this->files,
            'metrics' => $this->metrics,
            'linked_issues' => $this->linkedIssues,
            'pr_comments' => $this->prComments,
            'repository_context' => $this->repositoryContext,
            'review_history' => $this->reviewHistory,
            'guidelines' => $this->guidelines,
            'file_contents' => $this->fileContents,
            'semantics' => $this->semantics,
            'project_context' => $this->projectContext,
            'metadata' => $this->metadata,
        ];
    }
}
