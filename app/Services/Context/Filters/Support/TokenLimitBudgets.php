<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

/**
 * Typed token budgets for context sections.
 */
final readonly class TokenLimitBudgets
{
    /**
     * Create a new instance.
     */
    public function __construct(
        public int $filesTotal,
        public int $filesPer,
        public int $impactedFiles,
        public int $fileContents,
        public int $semantics,
        public int $issues,
        public int $comments,
        public int $guidelines,
        public int $repositoryContext,
        public int $reviewHistory,
        public int $projectContext,
    ) {}
}
