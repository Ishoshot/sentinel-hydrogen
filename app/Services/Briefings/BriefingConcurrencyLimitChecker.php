<?php

declare(strict_types=1);

namespace App\Services\Briefings;

use App\Enums\Briefings\BriefingGenerationStatus;
use App\Enums\Briefings\BriefingLimitReasonCode;
use App\Models\BriefingGeneration;
use App\Models\Workspace;
use App\Services\Briefings\ValueObjects\BriefingLimitResult;
use Illuminate\Support\Facades\DB;

final class BriefingConcurrencyLimitChecker
{
    /**
     * Check the concurrent generation limit using pessimistic locking.
     */
    public function check(Workspace $workspace): BriefingLimitResult
    {
        $limit = (int) config('briefings.limits.max_concurrent_generations', 3);

        return DB::transaction(function () use ($workspace, $limit): BriefingLimitResult {
            $pendingCount = BriefingGeneration::query()
                ->where('workspace_id', $workspace->id)
                ->whereIn('status', [
                    BriefingGenerationStatus::Pending,
                    BriefingGenerationStatus::Processing,
                ])
                ->lockForUpdate()
                ->count();

            if ($pendingCount >= $limit) {
                $message = sprintf(
                    '%d briefing%s currently generating. Please wait for %s to complete.',
                    $pendingCount,
                    $pendingCount === 1 ? ' is' : 's are',
                    $pendingCount === 1 ? 'it' : 'them',
                );

                return BriefingLimitResult::deny(
                    $message,
                    BriefingLimitReasonCode::ConcurrentLimitReached,
                    $message,
                );
            }

            return BriefingLimitResult::allow();
        });
    }
}
