<?php

declare(strict_types=1);

namespace App\Services\Reviews\Contracts;

use App\Models\Repository;
use App\Services\Context\ContextBag;

interface ReviewEngine
{
    /**
     * @param  array{repository: Repository, policy_snapshot: array<string, mixed>, context_bag: ContextBag}  $context
     * @return array{summary: array<string, mixed>, findings: array<int, array<string, mixed>>, metrics: array{files_changed: int, lines_added: int, lines_deleted: int, input_tokens: int, output_tokens: int, tokens_used_estimated: int, model: string, provider: string, duration_ms: int}}
     */
    public function review(array $context): array;
}
