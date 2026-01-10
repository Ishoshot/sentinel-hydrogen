<?php

declare(strict_types=1);

namespace App\Services\Reviews\Contracts;

use App\Models\Repository;
use App\Models\Run;

interface ReviewEngine
{
    /**
     * @param  array{run: Run, repository: Repository, policy_snapshot: array<string, mixed>, pull_request: array{number: int, title: string, body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, repository_full_name: string}, files: array<int, array{filename: string, additions: int, deletions: int, changes: int}>, metrics: array{files_changed: int, lines_added: int, lines_deleted: int}}  $context
     * @return array{summary: array{overview: string, risk_level: string, recommendations: array<int, string>}, findings: array<int, array{severity: string, category: string, title: string, description: string, rationale: string, confidence: float, file_path?: string, line_start?: int, line_end?: int, suggestion?: string, patch?: string, references?: array<int, string>, tags?: array<int, string>}>, metrics: array{files_changed: int, lines_added: int, lines_deleted: int, tokens_used_estimated: int, model: string, provider: string, duration_ms: int}}
     */
    public function review(array $context): array;
}
