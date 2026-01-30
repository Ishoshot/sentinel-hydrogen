<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Enums\Reviews\ReviewVerdict;
use App\Enums\Reviews\RiskLevel;
use App\Services\Context\ContextBag;
use App\Services\Reviews\Contracts\ReviewEngine;
use App\Services\Reviews\ValueObjects\ReviewMetrics;
use App\Services\Reviews\ValueObjects\ReviewResult;
use App\Services\Reviews\ValueObjects\ReviewSummary;

final readonly class DefaultReviewEngine implements ReviewEngine
{
    /**
     * @param  array{policy_snapshot: array<string, mixed>, context_bag: ContextBag}  $context
     */
    public function review(array $context): ReviewResult
    {
        $metrics = $context['context_bag']->metrics;

        return new ReviewResult(
            summary: new ReviewSummary(
                overview: 'Review completed with no findings.',
                verdict: ReviewVerdict::Approve,
                riskLevel: RiskLevel::Low,
                strengths: [],
                concerns: [],
                recommendations: [],
            ),
            findings: [],
            metrics: new ReviewMetrics(
                filesChanged: $metrics['files_changed'] ?? 0,
                linesAdded: $metrics['lines_added'] ?? 0,
                linesDeleted: $metrics['lines_deleted'] ?? 0,
                inputTokens: 0,
                outputTokens: 0,
                tokensUsedEstimated: 0,
                model: 'rule-based',
                provider: 'internal',
                durationMs: 0,
            ),
        );
    }
}
