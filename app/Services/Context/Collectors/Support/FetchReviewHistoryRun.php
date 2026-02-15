<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

use App\Enums\Reviews\RunStatus;
use App\Models\Repository;
use App\Models\Run;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Fetches previous runs eligible for review history context.
 */
final readonly class FetchReviewHistoryRun
{
    /**
     * @return Collection<int, Run>
     */
    public function fetch(
        Repository $repository,
        Run $currentRun,
        int $prNumber,
        int $maxReviews,
        int $maxFindingsPerReview,
        RunStatus $completedStatus
    ): Collection {
        return Run::query()
            ->where('repository_id', $repository->id)
            ->where('id', '!=', $currentRun->id)
            ->where('status', $completedStatus)
            ->whereJsonContains('metadata->pull_request_number', $prNumber)
            ->with(['findings' => static function (Relation $query) use ($maxFindingsPerReview): void {
                $query->orderBy('severity')->limit($maxFindingsPerReview);
            }])
            ->orderBy('created_at', 'desc')
            ->limit($maxReviews)
            ->get();
    }
}
