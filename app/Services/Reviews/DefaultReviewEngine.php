<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Services\Context\ContextBag;
use App\Services\Reviews\Contracts\ReviewEngine;

final readonly class DefaultReviewEngine implements ReviewEngine
{
    /**
     * @param  array{policy_snapshot: array<string, mixed>, context_bag: ContextBag}  $context
     * @return array{summary: array{overview: string, risk_level: string, recommendations: array<int, string>}, findings: array<int, array{severity: string, category: string, title: string, description: string, impact: string, confidence: float, file_path?: string, line_start?: int, line_end?: int, current_code?: string, replacement_code?: string, explanation?: string, references?: array<int, string>}>, metrics: array{files_changed: int, lines_added: int, lines_deleted: int, input_tokens: int, output_tokens: int, tokens_used_estimated: int, model: string, provider: string, duration_ms: int}}
     */
    public function review(array $context): array
    {
        $metrics = $context['context_bag']->metrics;

        return [
            'summary' => [
                'overview' => 'Review completed with no findings.',
                'risk_level' => 'low',
                'recommendations' => [],
            ],
            'findings' => [],
            'metrics' => [
                'files_changed' => $metrics['files_changed'] ?? 0,
                'lines_added' => $metrics['lines_added'] ?? 0,
                'lines_deleted' => $metrics['lines_deleted'] ?? 0,
                'input_tokens' => 0,
                'output_tokens' => 0,
                'tokens_used_estimated' => 0,
                'model' => 'rule-based',
                'provider' => 'internal',
                'duration_ms' => 0,
            ],
        ];
    }
}
