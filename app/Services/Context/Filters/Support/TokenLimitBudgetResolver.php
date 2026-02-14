<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

/**
 * Resolves section token budgets for context truncation.
 */
final class TokenLimitBudgetResolver
{
    /**
     * Fallback maximum context tokens when no budget is provided via metadata.
     */
    private const int DEFAULT_MAX_CONTEXT_TOKENS = 80000;

    /**
     * Minimum context budget to avoid over-truncating small prompts.
     */
    private const int MIN_CONTEXT_TOKENS = 8000;

    /**
     * Minimum token budget for a section.
     */
    private const int MIN_SECTION_TOKENS = 500;

    private const float RATIO_FILES_TOTAL = 0.45;

    private const float RATIO_FILES_PER = 0.08;

    private const float RATIO_IMPACTED_FILES = 0.12;

    private const float RATIO_ISSUES = 0.08;

    private const float RATIO_COMMENTS = 0.04;

    private const float RATIO_GUIDELINES = 0.06;

    private const float RATIO_REPOSITORY_CONTEXT = 0.05;

    private const float RATIO_REVIEW_HISTORY = 0.05;

    private const float RATIO_PROJECT_CONTEXT = 0.03;

    private const float RATIO_FILE_CONTENTS = 0.10;

    private const float RATIO_SEMANTICS = 0.05;

    private const int MAX_FILES_TOTAL = 150000;

    private const int MAX_FILES_PER = 20000;

    private const int MAX_IMPACTED_FILES = 40000;

    private const int MAX_FILE_CONTENTS = 30000;

    private const int MAX_SEMANTICS = 15000;

    /**
     * Resolve max context tokens from filter metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function resolveMaxContextTokens(array $metadata): int
    {
        $budget = $metadata['context_token_budget'] ?? null;

        if (is_int($budget) && $budget > 0) {
            return max($budget, self::MIN_CONTEXT_TOKENS);
        }

        if (is_string($budget) && is_numeric($budget)) {
            return max((int) $budget, self::MIN_CONTEXT_TOKENS);
        }

        return self::DEFAULT_MAX_CONTEXT_TOKENS;
    }

    /**
     * Compute per-section budgets from the max context budget.
     */
    public function resolveBudgets(int $maxContextTokens): TokenLimitBudgets
    {
        $filesTotal = $this->scaleBudget($maxContextTokens, self::RATIO_FILES_TOTAL, self::MAX_FILES_TOTAL);
        $filesPer = $this->scaleBudget($maxContextTokens, self::RATIO_FILES_PER, self::MAX_FILES_PER);
        $impactedFiles = $this->scaleBudget($maxContextTokens, self::RATIO_IMPACTED_FILES, self::MAX_IMPACTED_FILES);
        $fileContents = $this->scaleBudget($maxContextTokens, self::RATIO_FILE_CONTENTS, self::MAX_FILE_CONTENTS);
        $semantics = $this->scaleBudget($maxContextTokens, self::RATIO_SEMANTICS, self::MAX_SEMANTICS);
        $issues = $this->scaleBudget($maxContextTokens, self::RATIO_ISSUES);
        $comments = $this->scaleBudget($maxContextTokens, self::RATIO_COMMENTS);
        $guidelines = $this->scaleBudget($maxContextTokens, self::RATIO_GUIDELINES);
        $repositoryContext = $this->scaleBudget($maxContextTokens, self::RATIO_REPOSITORY_CONTEXT);
        $reviewHistory = $this->scaleBudget($maxContextTokens, self::RATIO_REVIEW_HISTORY);
        $projectContext = $this->scaleBudget($maxContextTokens, self::RATIO_PROJECT_CONTEXT);

        if ($filesPer > $filesTotal) {
            $filesPer = $filesTotal;
        }

        return new TokenLimitBudgets(
            filesTotal: $filesTotal,
            filesPer: $filesPer,
            impactedFiles: $impactedFiles,
            fileContents: $fileContents,
            semantics: $semantics,
            issues: $issues,
            comments: $comments,
            guidelines: $guidelines,
            repositoryContext: $repositoryContext,
            reviewHistory: $reviewHistory,
            projectContext: $projectContext,
        );
    }

    /**
     * Scale a budget by ratio with minimum floor and optional cap.
     */
    private function scaleBudget(int $maxContextTokens, float $ratio, ?int $maxCap = null): int
    {
        $scaled = max((int) round($maxContextTokens * $ratio), self::MIN_SECTION_TOKENS);

        if ($maxCap !== null) {
            return min($scaled, $maxCap);
        }

        return $scaled;
    }
}
