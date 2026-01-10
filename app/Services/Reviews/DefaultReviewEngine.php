<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Services\Reviews\Contracts\ReviewEngine;

final readonly class DefaultReviewEngine implements ReviewEngine
{
    /**
     * @param  array{run: \App\Models\Run, repository: \App\Models\Repository, policy_snapshot: array<string, mixed>, pull_request: array{number: int, title: string, body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, repository_full_name: string}, files: array<int, array{filename: string, additions: int, deletions: int, changes: int}>, metrics: array{files_changed: int, lines_added: int, lines_deleted: int}}  $context
     * @return array{summary: array{overview: string, risk_level: string, recommendations: array<int, string>}, findings: array<int, array{severity: string, category: string, title: string, description: string, rationale: string, confidence: float, file_path?: string, line_start?: int, line_end?: int, suggestion?: string, patch?: string, references?: array<int, string>, tags?: array<int, string>}>, metrics: array{files_changed: int, lines_added: int, lines_deleted: int, tokens_used_estimated: int, model: string, provider: string, duration_ms: int}}
     */
    public function review(array $context): array
    {
        $metrics = $context['metrics'];

        return [
            'summary' => [
                'overview' => 'Review completed with no findings.',
                'risk_level' => 'low',
                'recommendations' => [],
            ],
            'findings' => [],
            'metrics' => [
                'files_changed' => $metrics['files_changed'],
                'lines_added' => $metrics['lines_added'],
                'lines_deleted' => $metrics['lines_deleted'],
                'tokens_used_estimated' => 0,
                'model' => 'rule-based',
                'provider' => 'internal',
                'duration_ms' => 0,
            ],
        ];
    }
}
