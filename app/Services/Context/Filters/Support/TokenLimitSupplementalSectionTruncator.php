<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

use App\Services\Context\TokenCounting\TokenCounterContext;

/**
 * Truncates non-code context sections (issues, comments, docs, metadata).
 */
final readonly class TokenLimitSupplementalSectionTruncator
{
    /**
     * Create a new supplemental section truncator instance.
     */
    public function __construct(
        private TokenLimitIssueSectionTruncator $issueSectionTruncator,
        private TokenLimitCommentSectionTruncator $commentSectionTruncator,
        private TokenLimitGuidelineSectionTruncator $guidelineSectionTruncator,
        private TokenLimitRepositoryContextTruncator $repositoryContextTruncator,
        private TokenLimitReviewHistoryTruncator $reviewHistoryTruncator,
        private TokenLimitProjectContextTruncator $projectContextTruncator,
    ) {}

    /**
     * Set token counting context for the current truncation cycle.
     */
    public function setContext(TokenCounterContext $tokenCounterContext): void
    {
        $this->issueSectionTruncator->setContext($tokenCounterContext);
        $this->commentSectionTruncator->setContext($tokenCounterContext);
        $this->guidelineSectionTruncator->setContext($tokenCounterContext);
        $this->repositoryContextTruncator->setContext($tokenCounterContext);
        $this->reviewHistoryTruncator->setContext($tokenCounterContext);
        $this->projectContextTruncator->setContext($tokenCounterContext);
    }

    /**
     * Truncate linked issues to fit within budget.
     *
     * @param  array<int, array{number: int, title: string, body: string|null, state: string, labels: array<int, string>, comments: array<int, array{author: string, body: string}>}>  $issues
     * @return array<int, array{number: int, title: string, body: string|null, state: string, labels: array<int, string>, comments: array<int, array{author: string, body: string}>}>
     */
    public function truncateLinkedIssues(array $issues, int $maxTokens): array
    {
        return $this->issueSectionTruncator->truncate($issues, $maxTokens);
    }

    /**
     * Truncate PR comments to fit within budget.
     *
     * @param  array<int, array{author: string, body: string, created_at: string}>  $comments
     * @return array<int, array{author: string, body: string, created_at: string}>
     */
    public function truncatePrComments(array $comments, int $maxTokens): array
    {
        return $this->commentSectionTruncator->truncate($comments, $maxTokens);
    }

    /**
     * Truncate guidelines to fit within a token budget.
     *
     * @param  array<int, array{path: string, description: string|null, content: string}>  $guidelines
     * @return array<int, array{path: string, description: string|null, content: string}>
     */
    public function truncateGuidelines(array $guidelines, int $maxTokens): array
    {
        return $this->guidelineSectionTruncator->truncate($guidelines, $maxTokens);
    }

    /**
     * Truncate repository context to fit within a token budget.
     *
     * @param  array{readme?: string|null, contributing?: string|null}  $context
     * @return array{readme?: string|null, contributing?: string|null}
     */
    public function truncateRepositoryContext(array $context, int $maxTokens): array
    {
        return $this->repositoryContextTruncator->truncate($context, $maxTokens);
    }

    /**
     * Truncate review history to fit within a token budget.
     *
     * @param  array<int, array{run_id: int, summary: string, findings_count: int, severity_breakdown: array<string, int>, key_findings: array<int, array{severity: string, category: string, title: string, file_path: string|null, line_start: int|null, fingerprint: string}>, created_at: string}>  $reviews
     * @return array<int, array{run_id: int, summary: string, findings_count: int, severity_breakdown: array<string, int>, key_findings: array<int, array{severity: string, category: string, title: string, file_path: string|null, line_start: int|null, fingerprint: string}>, created_at: string}>
     */
    public function truncateReviewHistory(array $reviews, int $maxTokens): array
    {
        return $this->reviewHistoryTruncator->truncate($reviews, $maxTokens);
    }

    /**
     * Truncate project context to fit within a token budget.
     *
     * @param  array{languages?: array<string>, runtime?: array{name: string, version: string}|null, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}  $context
     * @return array{languages?: array<string>, runtime?: array{name: string, version: string}|null, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}
     */
    public function truncateProjectContext(array $context, int $maxTokens): array
    {
        return $this->projectContextTruncator->truncate($context, $maxTokens);
    }
}
