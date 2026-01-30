<?php

declare(strict_types=1);

namespace App\Services\Reviews\Contracts;

use App\Models\Repository;
use App\Services\Context\ContextBag;
use App\Services\Reviews\ValueObjects\ReviewResult;

interface ReviewEngine
{
    /**
     * @param  array{repository: Repository, policy_snapshot: array<string, mixed>, context_bag: ContextBag}  $context
     */
    public function review(array $context): ReviewResult;
}
