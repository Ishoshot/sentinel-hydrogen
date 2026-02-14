<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\TokenCounter;
use App\Services\Context\TokenCounting\TokenCounterContext;

/**
 * Performs token-aware truncation for context sections.
 */
final class TokenLimitSectionTruncator
{
    /**
     * Context manager for token counting state and propagation.
     */
    private readonly TokenLimitContextManager $contextManager;

    /**
     * Create a new section truncator instance.
     */
    public function __construct(
        private readonly TokenCounter $tokenCounter,
        private readonly TokenLimitFilePatchTruncator $filePatchTruncator,
        private readonly TokenLimitCodeSectionTruncator $codeSectionTruncator,
        private readonly TokenLimitSupplementalSectionTruncator $supplementalSectionTruncator,
        private readonly TokenLimitProgressiveTruncator $progressiveTruncator,
        ?TokenLimitContextManager $contextManager = null,
    ) {
        $this->contextManager = $contextManager ?? new TokenLimitContextManager(
            $this->tokenCounter,
            $this->filePatchTruncator,
            $this->codeSectionTruncator,
            $this->supplementalSectionTruncator,
        );
    }

    /**
     * Set the token counter context for the current truncation cycle.
     */
    public function setContext(TokenCounterContext $tokenCounterContext): void
    {
        $this->contextManager->setContext($tokenCounterContext);
    }

    /**
     * Estimate current context bag tokens using the active token counter context.
     */
    public function estimateBagTokens(ContextBag $bag): int
    {
        return $this->contextManager->estimateBagTokens($bag);
    }

    /**
     * Truncate individual file patches that exceed per-file and total limits.
     *
     * @param  array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>  $files
     * @return array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>
     */
    public function truncateFiles(array $files, int $maxTokensPerFile, int $maxTokensAllFiles): array
    {
        return $this->filePatchTruncator->truncateFiles($files, $maxTokensPerFile, $maxTokensAllFiles);
    }

    /**
     * Truncate linked issues to fit within budget.
     *
     * @param  array<int, array{number: int, title: string, body: string|null, state: string, labels: array<int, string>, comments: array<int, array{author: string, body: string}>}>  $issues
     * @return array<int, array{number: int, title: string, body: string|null, state: string, labels: array<int, string>, comments: array<int, array{author: string, body: string}>}>
     */
    public function truncateLinkedIssues(array $issues, int $maxTokens): array
    {
        return $this->supplementalSectionTruncator->truncateLinkedIssues($issues, $maxTokens);
    }

    /**
     * Truncate PR comments to fit within budget.
     *
     * @param  array<int, array{author: string, body: string, created_at: string}>  $comments
     * @return array<int, array{author: string, body: string, created_at: string}>
     */
    public function truncatePrComments(array $comments, int $maxTokens): array
    {
        return $this->supplementalSectionTruncator->truncatePrComments($comments, $maxTokens);
    }

    /**
     * Truncate guidelines to fit within a token budget.
     *
     * @param  array<int, array{path: string, description: string|null, content: string}>  $guidelines
     * @return array<int, array{path: string, description: string|null, content: string}>
     */
    public function truncateGuidelines(array $guidelines, int $maxTokens): array
    {
        return $this->supplementalSectionTruncator->truncateGuidelines($guidelines, $maxTokens);
    }

    /**
     * Truncate repository context to fit within a token budget.
     *
     * @param  array{readme?: string|null, contributing?: string|null}  $context
     * @return array{readme?: string|null, contributing?: string|null}
     */
    public function truncateRepositoryContext(array $context, int $maxTokens): array
    {
        return $this->supplementalSectionTruncator->truncateRepositoryContext($context, $maxTokens);
    }

    /**
     * Truncate review history to fit within a token budget.
     *
     * @param  array<int, array{run_id: int, summary: string, findings_count: int, severity_breakdown: array<string, int>, key_findings: array<int, array{severity: string, category: string, title: string, file_path: string|null, line_start: int|null, fingerprint: string}>, created_at: string}>  $reviews
     * @return array<int, array{run_id: int, summary: string, findings_count: int, severity_breakdown: array<string, int>, key_findings: array<int, array{severity: string, category: string, title: string, file_path: string|null, line_start: int|null, fingerprint: string}>, created_at: string}>
     */
    public function truncateReviewHistory(array $reviews, int $maxTokens): array
    {
        return $this->supplementalSectionTruncator->truncateReviewHistory($reviews, $maxTokens);
    }

    /**
     * Truncate project context to fit within a token budget.
     *
     * @param  array{languages?: array<string>, runtime?: array{name: string, version: string}|null, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}  $context
     * @return array{languages?: array<string>, runtime?: array{name: string, version: string}|null, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}
     */
    public function truncateProjectContext(array $context, int $maxTokens): array
    {
        return $this->supplementalSectionTruncator->truncateProjectContext($context, $maxTokens);
    }

    /**
     * Truncate impacted files to fit within a token budget.
     *
     * @param  array<int, array{file_path: string, content: string, matched_symbol: string, match_type: string, score: float, match_count: int, reason: string}>  $impactedFiles
     * @return array<int, array{file_path: string, content: string, matched_symbol: string, match_type: string, score: float, match_count: int, reason: string}>
     */
    public function truncateImpactedFiles(array $impactedFiles, int $maxTokens): array
    {
        return $this->codeSectionTruncator->truncateImpactedFiles($impactedFiles, $maxTokens);
    }

    /**
     * Truncate full file contents to fit within a token budget.
     *
     * @param  array<string, string>  $fileContents
     * @return array<string, string>
     */
    public function truncateFileContents(array $fileContents, int $maxTokens): array
    {
        return $this->codeSectionTruncator->truncateFileContents($fileContents, $maxTokens);
    }

    /**
     * Truncate semantic analysis data to fit within a token budget.
     *
     * @param  array<string, array<string, mixed>>  $semantics
     * @return array<string, array<string, mixed>>
     */
    public function truncateSemantics(array $semantics, int $maxTokens): array
    {
        return $this->codeSectionTruncator->truncateSemantics($semantics, $maxTokens);
    }

    /**
     * Progressive truncation of lower priority content when still above max budget.
     */
    public function progressiveTruncation(ContextBag $bag, int $maxContextTokens): void
    {
        $this->progressiveTruncator->progressiveTruncation(
            $bag,
            $maxContextTokens,
            fn (ContextBag $candidateBag, int $maxTokens): bool => $this->contextManager->isOverBudget($candidateBag, $maxTokens)
        );
    }
}
