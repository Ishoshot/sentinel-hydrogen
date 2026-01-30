<?php

declare(strict_types=1);

namespace App\Services\Context;

use App\Services\Context\Contracts\TokenCounter;
use App\Services\Context\TokenCounting\HeuristicTokenCounter;
use App\Services\Context\TokenCounting\TokenCounterContext;

/**
 * Data transfer object holding all context data for a review.
 */
final class ContextBag
{
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
     * @param  array<int, array{file_path: string, content: string, matched_symbol: string, match_type: string, score: float, match_count: int, reason: string}>  $impactedFiles  Files that reference modified symbols
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
        public array $impactedFiles = [],
        public array $metadata = [],
    ) {}

    /**
     * Estimate the total token count for this context.
     */
    public function estimateTokens(?TokenCounter $tokenCounter = null, ?TokenCounterContext $context = null): int
    {
        $tokenCounter ??= new HeuristicTokenCounter();
        $context ??= new TokenCounterContext();
        $totalTokens = 0;

        // Pull request metadata
        $totalTokens += $tokenCounter->countTextTokens(json_encode($this->pullRequest) ?: '', $context);

        // Files with patches (most significant)
        foreach ($this->files as $file) {
            $totalTokens += $tokenCounter->countTextTokens($file['filename'], $context);
            $totalTokens += $tokenCounter->countTextTokens($file['patch'] ?? '', $context);
        }

        // Metrics
        $totalTokens += $tokenCounter->countTextTokens(json_encode($this->metrics) ?: '', $context);

        // Linked issues
        foreach ($this->linkedIssues as $issue) {
            $totalTokens += $tokenCounter->countTextTokens($issue['title'], $context);
            $totalTokens += $tokenCounter->countTextTokens($issue['body'] ?? '', $context);
            foreach ($issue['comments'] as $comment) {
                $totalTokens += $tokenCounter->countTextTokens($comment['body'], $context);
            }
        }

        // PR comments
        foreach ($this->prComments as $comment) {
            $totalTokens += $tokenCounter->countTextTokens($comment['body'], $context);
        }

        // Repository context
        $totalTokens += $tokenCounter->countTextTokens($this->repositoryContext['readme'] ?? '', $context);
        $totalTokens += $tokenCounter->countTextTokens($this->repositoryContext['contributing'] ?? '', $context);

        // Review history
        foreach ($this->reviewHistory as $review) {
            $totalTokens += $tokenCounter->countTextTokens($review['summary'], $context);
        }

        // Guidelines
        foreach ($this->guidelines as $guideline) {
            $totalTokens += $tokenCounter->countTextTokens($guideline['content'], $context);
        }

        // File contents
        foreach ($this->fileContents as $content) {
            $totalTokens += $tokenCounter->countTextTokens($content, $context);
        }

        // Semantic analysis data
        foreach ($this->semantics as $data) {
            $totalTokens += $tokenCounter->countTextTokens(json_encode($data) ?: '', $context);
        }

        // Project context
        $totalTokens += $tokenCounter->countTextTokens(json_encode($this->projectContext) ?: '', $context);

        // Impacted files
        foreach ($this->impactedFiles as $impactedFile) {
            $totalTokens += $tokenCounter->countTextTokens($impactedFile['content'], $context);
        }

        return $totalTokens;
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
            'impacted_files' => $this->impactedFiles,
            'metadata' => $this->metadata,
        ];
    }
}
