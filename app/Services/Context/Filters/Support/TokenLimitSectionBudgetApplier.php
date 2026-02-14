<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

use App\Services\Context\ContextBag;

/**
 * Applies per-section token budgets to a context bag.
 */
final readonly class TokenLimitSectionBudgetApplier
{
    /**
     * Create a new section budget applier instance.
     */
    public function __construct(private TokenLimitSectionTruncator $sectionTruncator) {}

    /**
     * Apply section truncation based on resolved budgets.
     */
    public function apply(ContextBag $bag, TokenLimitBudgets $budgets): void
    {
        $bag->files = $this->sectionTruncator->truncateFiles(
            $bag->files,
            $budgets->filesPer,
            $budgets->filesTotal
        );
        $bag->impactedFiles = $this->sectionTruncator->truncateImpactedFiles($bag->impactedFiles, $budgets->impactedFiles);
        $bag->fileContents = $this->sectionTruncator->truncateFileContents($bag->fileContents, $budgets->fileContents);
        $bag->semantics = $this->sectionTruncator->truncateSemantics($bag->semantics, $budgets->semantics);
        $bag->linkedIssues = $this->sectionTruncator->truncateLinkedIssues($bag->linkedIssues, $budgets->issues);
        $bag->prComments = $this->sectionTruncator->truncatePrComments($bag->prComments, $budgets->comments);
        $bag->guidelines = $this->sectionTruncator->truncateGuidelines($bag->guidelines, $budgets->guidelines);
        $bag->repositoryContext = $this->sectionTruncator->truncateRepositoryContext($bag->repositoryContext, $budgets->repositoryContext);
        $bag->reviewHistory = $this->sectionTruncator->truncateReviewHistory($bag->reviewHistory, $budgets->reviewHistory);
        $bag->projectContext = $this->sectionTruncator->truncateProjectContext($bag->projectContext, $budgets->projectContext);
    }
}
