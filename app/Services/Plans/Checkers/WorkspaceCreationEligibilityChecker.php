<?php

declare(strict_types=1);

namespace App\Services\Plans\Checkers;

use App\Enums\Billing\PlanTier;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Plans\ValueObjects\PlanLimitResult;

final readonly class WorkspaceCreationEligibilityChecker
{
    /**
     * Ensure a user can create another workspace under current plan constraints.
     */
    public function ensureCanCreate(User $user): PlanLimitResult
    {
        $ownedWorkspaces = Workspace::query()
            ->where('owner_id', $user->id)
            ->with('plan')
            ->get();

        if ($ownedWorkspaces->isEmpty()) {
            return PlanLimitResult::allow();
        }

        foreach ($ownedWorkspaces as $workspace) {
            $plan = $workspace->plan;
            $tier = $plan !== null ? PlanTier::tryFrom($plan->tier) : PlanTier::Foundation;

            if ($tier === null || $tier->isFree()) {
                $message = 'To create additional workspaces, all your existing workspaces must be on a paid plan (Illuminate or higher).';

                return PlanLimitResult::deny($message, 'paid_plan_required');
            }
        }

        return PlanLimitResult::allow();
    }
}
