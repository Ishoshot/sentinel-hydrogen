<?php

declare(strict_types=1);

namespace App\Services\Context\Filters;

use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextFilter;
use Illuminate\Support\Facades\Log;

/**
 * Limits context to fit within LLM token constraints.
 *
 * Truncates context starting with lowest priority content (repository context,
 * review history) and working up to preserve the most important content (code diffs).
 */
final class TokenLimitFilter implements ContextFilter
{
    /**
     * Approximate tokens per character for estimation.
     */
    private const float TOKENS_PER_CHAR = 0.25;

    /**
     * Maximum allowed tokens for context (leaving room for system prompt and response).
     * Claude 3.5 Sonnet has ~200K context, we use ~80K for context leaving room for
     * system prompt (~2K) and response (~10K).
     */
    private const int MAX_CONTEXT_TOKENS = 80000;

    /**
     * Maximum tokens per individual file patch.
     */
    private const int MAX_TOKENS_PER_FILE = 8000;

    /**
     * Maximum total tokens for all file patches.
     */
    private const int MAX_TOKENS_ALL_FILES = 60000;

    /**
     * Maximum tokens for linked issues section.
     */
    private const int MAX_TOKENS_ISSUES = 10000;

    /**
     * Maximum tokens for PR comments section.
     */
    private const int MAX_TOKENS_COMMENTS = 5000;

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'token_limit';
    }

    /**
     * {@inheritdoc}
     */
    public function order(): int
    {
        return 100; // Run last to truncate after all other filters
    }

    /**
     * {@inheritdoc}
     */
    public function filter(ContextBag $bag): void
    {
        $initialTokens = $bag->estimateTokens();

        // Truncate individual file patches that are too large
        $bag->files = $this->truncateFiles($bag->files);

        // Truncate linked issues if needed
        $bag->linkedIssues = $this->truncateLinkedIssues($bag->linkedIssues);

        // Truncate PR comments if needed
        $bag->prComments = $this->truncatePrComments($bag->prComments);

        // If still over limit, progressively remove lower priority content
        if ($bag->estimateTokens() > self::MAX_CONTEXT_TOKENS) {
            $this->progressiveTruncation($bag);
        }

        $finalTokens = $bag->estimateTokens();

        if ($initialTokens !== $finalTokens) {
            Log::info('TokenLimitFilter: Truncated context', [
                'initial_tokens' => $initialTokens,
                'final_tokens' => $finalTokens,
                'reduction' => $initialTokens - $finalTokens,
            ]);
        }
    }

    /**
     * Truncate individual file patches that exceed the per-file limit.
     *
     * @param  array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>  $files
     * @return array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>
     */
    private function truncateFiles(array $files): array
    {
        $totalTokens = 0;

        return array_map(function (array $file) use (&$totalTokens): array {
            $patch = $file['patch'];

            if ($patch === null) {
                return $file;
            }

            $patchTokens = $this->estimateTokens($patch);

            // Truncate individual large patches
            if ($patchTokens > self::MAX_TOKENS_PER_FILE) {
                $maxChars = (int) (self::MAX_TOKENS_PER_FILE / self::TOKENS_PER_CHAR);
                $file['patch'] = mb_substr($patch, 0, $maxChars)."\n... [truncated - file too large]";
                $patchTokens = self::MAX_TOKENS_PER_FILE;
            }

            // Check if we're approaching the total file limit
            if ($totalTokens + $patchTokens > self::MAX_TOKENS_ALL_FILES) {
                // Truncate this patch to fit remaining budget
                $remainingBudget = self::MAX_TOKENS_ALL_FILES - $totalTokens;
                if ($remainingBudget > 500) {
                    $maxChars = (int) ($remainingBudget / self::TOKENS_PER_CHAR);
                    $file['patch'] = mb_substr($patch, 0, $maxChars)."\n... [truncated - token limit]";
                    $patchTokens = $remainingBudget;
                } else {
                    // No room left, remove the patch entirely
                    $file['patch'] = '[patch omitted - token limit reached]';
                    $patchTokens = 50;
                }
            }

            $totalTokens += $patchTokens;

            return $file;
        }, $files);
    }

    /**
     * Truncate linked issues to fit within budget.
     *
     * @param  array<int, array{number: int, title: string, body: string|null, state: string, labels: array<int, string>, comments: array<int, array{author: string, body: string}>}>  $issues
     * @return array<int, array{number: int, title: string, body: string|null, state: string, labels: array<int, string>, comments: array<int, array{author: string, body: string}>}>
     */
    private function truncateLinkedIssues(array $issues): array
    {
        $totalTokens = 0;
        $result = [];

        foreach ($issues as $issue) {
            $issueTokens = $this->estimateIssueTokens($issue);

            if ($totalTokens + $issueTokens > self::MAX_TOKENS_ISSUES) {
                // Truncate this issue's body and comments
                if ($totalTokens < self::MAX_TOKENS_ISSUES - 500) {
                    $remainingBudget = self::MAX_TOKENS_ISSUES - $totalTokens - 200; // Reserve for metadata
                    $issue = $this->truncateIssue($issue, $remainingBudget);
                    $result[] = $issue;
                }

                break;
            }

            $result[] = $issue;
            $totalTokens += $issueTokens;
        }

        return $result;
    }

    /**
     * Truncate a single issue to fit within a token budget.
     *
     * @param  array{number: int, title: string, body: string|null, state: string, labels: array<int, string>, comments: array<int, array{author: string, body: string}>}  $issue
     * @return array{number: int, title: string, body: string|null, state: string, labels: array<int, string>, comments: array<int, array{author: string, body: string}>}
     */
    private function truncateIssue(array $issue, int $maxTokens): array
    {
        // Truncate body if needed
        if ($issue['body'] !== null) {
            $bodyTokens = $this->estimateTokens($issue['body']);
            if ($bodyTokens > $maxTokens / 2) {
                $maxChars = (int) (($maxTokens / 2) / self::TOKENS_PER_CHAR);
                $issue['body'] = mb_substr($issue['body'], 0, $maxChars).'... [truncated]';
            }
        }

        // Limit comments
        $issue['comments'] = array_slice($issue['comments'], 0, 3);

        return $issue;
    }

    /**
     * Truncate PR comments to fit within budget.
     *
     * @param  array<int, array{author: string, body: string, created_at: string}>  $comments
     * @return array<int, array{author: string, body: string, created_at: string}>
     */
    private function truncatePrComments(array $comments): array
    {
        $totalTokens = 0;
        $result = [];

        foreach ($comments as $comment) {
            $commentTokens = $this->estimateTokens($comment['body']);

            if ($totalTokens + $commentTokens > self::MAX_TOKENS_COMMENTS) {
                break;
            }

            $result[] = $comment;
            $totalTokens += $commentTokens;
        }

        return $result;
    }

    /**
     * Progressive truncation of lower priority content.
     */
    private function progressiveTruncation(ContextBag $bag): void
    {
        // Priority order (remove from bottom):
        // 1. Review history (lowest)
        // 2. Repository context
        // 3. PR comments
        // 4. Linked issues
        // 5. Files (highest - never fully remove)

        // Clear review history
        if ($bag->estimateTokens() > self::MAX_CONTEXT_TOKENS) {
            $bag->reviewHistory = [];
        }

        // Clear repository context
        if ($bag->estimateTokens() > self::MAX_CONTEXT_TOKENS) {
            $bag->repositoryContext = [];
        }

        // Reduce PR comments
        if ($bag->estimateTokens() > self::MAX_CONTEXT_TOKENS) {
            $bag->prComments = array_slice($bag->prComments, 0, 5);
        }

        // Reduce linked issues
        if ($bag->estimateTokens() > self::MAX_CONTEXT_TOKENS) {
            $bag->linkedIssues = array_slice($bag->linkedIssues, 0, 2);
        }

        // Final resort: truncate more file patches
        if ($bag->estimateTokens() > self::MAX_CONTEXT_TOKENS) {
            $bag->files = $this->aggressiveTruncateFiles($bag->files);
        }
    }

    /**
     * Aggressively truncate file patches when severely over limit.
     *
     * @param  array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>  $files
     * @return array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>
     */
    private function aggressiveTruncateFiles(array $files): array
    {
        // Keep first N files with patches, truncate the rest
        $filesWithPatches = 0;
        $maxFilesWithPatches = 15;

        return array_map(function (array $file) use (&$filesWithPatches, $maxFilesWithPatches): array {
            if ($file['patch'] !== null && $file['patch'] !== '') {
                $filesWithPatches++;

                if ($filesWithPatches > $maxFilesWithPatches) {
                    $file['patch'] = '[patch omitted - too many files]';
                } elseif (mb_strlen($file['patch']) > 2000) {
                    $file['patch'] = mb_substr($file['patch'], 0, 2000)."\n... [aggressively truncated]";
                }
            }

            return $file;
        }, $files);
    }

    /**
     * Estimate tokens for a string.
     */
    private function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) * self::TOKENS_PER_CHAR);
    }

    /**
     * Estimate tokens for an issue including its comments.
     *
     * @param  array{number: int, title: string, body: string|null, state: string, labels: array<int, string>, comments: array<int, array{author: string, body: string}>}  $issue
     */
    private function estimateIssueTokens(array $issue): int
    {
        $tokens = $this->estimateTokens($issue['title']);
        $tokens += $this->estimateTokens($issue['body'] ?? '');

        foreach ($issue['comments'] as $comment) {
            $tokens += $this->estimateTokens($comment['body']);
        }

        return $tokens;
    }
}
