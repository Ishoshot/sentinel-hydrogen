<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

use App\Services\Context\ContextBag;

/**
 * Applies progressively more aggressive reductions when still over budget.
 */
final readonly class TokenLimitProgressiveTruncator
{
    /**
     * Create a new class instance.
     */
    public function __construct(private TokenLimitFilePatchTruncator $filePatchTruncator) {}

    /**
     * Progressive truncation of lower priority content when still above max budget.
     *
     * @param  callable(ContextBag, int): bool  $isOverBudget
     */
    public function progressiveTruncation(ContextBag $bag, int $maxContextTokens, callable $isOverBudget): void
    {
        if ($isOverBudget($bag, $maxContextTokens)) {
            $bag->reviewHistory = [];
        }

        if ($isOverBudget($bag, $maxContextTokens)) {
            $bag->repositoryContext = [];
        }

        if ($isOverBudget($bag, $maxContextTokens)) {
            $bag->projectContext = [];
        }

        if ($isOverBudget($bag, $maxContextTokens)) {
            $bag->prComments = array_slice($bag->prComments, 0, 5);
        }

        if ($isOverBudget($bag, $maxContextTokens)) {
            $bag->prComments = [];
        }

        if ($isOverBudget($bag, $maxContextTokens)) {
            $bag->semantics = array_slice($bag->semantics, 0, 5, preserve_keys: true);
        }

        if ($isOverBudget($bag, $maxContextTokens)) {
            $bag->semantics = [];
        }

        if ($isOverBudget($bag, $maxContextTokens)) {
            $bag->linkedIssues = array_slice($bag->linkedIssues, 0, 2);
        }

        if ($isOverBudget($bag, $maxContextTokens)) {
            $bag->linkedIssues = [];
        }

        if ($isOverBudget($bag, $maxContextTokens)) {
            $bag->fileContents = array_slice($bag->fileContents, 0, 3, preserve_keys: true);
        }

        if ($isOverBudget($bag, $maxContextTokens)) {
            $bag->fileContents = [];
        }

        if ($isOverBudget($bag, $maxContextTokens)) {
            $bag->impactedFiles = array_slice($bag->impactedFiles, 0, 5);
        }

        if ($isOverBudget($bag, $maxContextTokens)) {
            $bag->impactedFiles = [];
        }

        if ($isOverBudget($bag, $maxContextTokens)) {
            $bag->guidelines = array_slice($bag->guidelines, 0, 1);
        }

        if ($isOverBudget($bag, $maxContextTokens)) {
            $bag->files = $this->filePatchTruncator->aggressiveTruncateFiles($bag->files);
        }
    }
}
