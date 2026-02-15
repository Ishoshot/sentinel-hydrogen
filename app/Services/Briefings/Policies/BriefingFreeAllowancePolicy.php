<?php

declare(strict_types=1);

namespace App\Services\Briefings\Policies;

use App\Enums\Briefings\BriefingGenerationStatus;
use App\Enums\Briefings\BriefingLimitReasonCode;
use App\Models\BriefingGeneration;
use App\Models\Workspace;
use App\Services\Briefings\ValueObjects\BriefingLimitResult;

final class BriefingFreeAllowancePolicy
{
    /**
     * Check the lifetime free allowance for workspaces without BYOK keys.
     */
    public function check(Workspace $workspace): BriefingLimitResult
    {
        $freeLimit = (int) config('briefings.limits.free_generations', 3);

        $completedCount = BriefingGeneration::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', BriefingGenerationStatus::Completed)
            ->count();

        if ($completedCount >= $freeLimit) {
            $guidance = 'Add a workspace API key to continue generating briefings.';

            $message = $freeLimit > 0
                ? sprintf(
                    "You've used all %d of your free briefings.",
                    $freeLimit,
                )
                : 'Free briefing generation is not available.';

            return BriefingLimitResult::deny($message, BriefingLimitReasonCode::FreeAllowanceExhausted, $guidance);
        }

        return BriefingLimitResult::allow();
    }
}
