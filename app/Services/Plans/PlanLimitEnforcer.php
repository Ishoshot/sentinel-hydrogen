<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Actions\Activities\LogActivity;
use App\Enums\ActivityType;
use App\Enums\PlanFeature;
use App\Enums\PlanTier;
use App\Enums\RunStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Run;
use App\Models\User;
use App\Models\Workspace;
use App\Support\PlanDefaults;
use Carbon\CarbonImmutable;

final readonly class PlanLimitEnforcer
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private LogActivity $logActivity,
    ) {}

    /**
     * Ensure the workspace has an active subscription.
     */
    public function ensureActiveSubscription(Workspace $workspace): PlanLimitResult
    {
        $status = $workspace->subscription_status;

        if ($status instanceof SubscriptionStatus && $status->isActive()) {
            return PlanLimitResult::allow();
        }

        $message = 'Your subscription is inactive. Upgrade to restore review access.';

        $this->logLimitEvent($workspace, 'subscription_inactive', $message);

        return PlanLimitResult::deny($message, 'subscription_inactive');
    }

    /**
     * Ensure the workspace can create a new run within its plan limits.
     */
    public function ensureRunAllowed(Workspace $workspace): PlanLimitResult
    {
        $activeCheck = $this->ensureActiveSubscription($workspace);

        if (! $activeCheck->allowed) {
            return $activeCheck;
        }

        $plan = $this->resolvePlan($workspace);
        $limit = $plan->monthly_runs_limit;

        if ($limit === null) {
            return PlanLimitResult::allow();
        }

        [$start, $end] = $this->currentPeriod();

        $runsCount = Run::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->whereIn('status', [
                RunStatus::Queued,
                RunStatus::InProgress,
                RunStatus::Completed,
                RunStatus::Failed,
            ])
            ->count();

        if ($runsCount < $limit) {
            return PlanLimitResult::allow();
        }

        $message = sprintf(
            'Run limit reached (%d/%d). Upgrade your plan to run more reviews.',
            $runsCount,
            $limit
        );

        $this->logLimitEvent($workspace, 'runs_limit', $message, [
            'runs_count' => $runsCount,
            'limit' => $limit,
        ]);

        return PlanLimitResult::deny($message, 'runs_limit');
    }

    /**
     * Ensure the workspace can invite a new team member.
     */
    public function ensureCanInviteMember(Workspace $workspace): PlanLimitResult
    {
        $plan = $this->resolvePlan($workspace);
        $limit = $plan->team_size_limit;

        if ($limit === null) {
            return PlanLimitResult::allow();
        }

        $teamSize = $workspace->teamMembers()->count();

        if ($teamSize < (int) $limit) {
            return PlanLimitResult::allow();
        }

        $message = sprintf(
            'Team size limit reached (%d/%d). Upgrade your plan to add more members.',
            $teamSize,
            $limit
        );

        $this->logLimitEvent($workspace, 'team_size_limit', $message, [
            'team_size' => $teamSize,
            'limit' => $limit,
        ]);

        return PlanLimitResult::deny($message, 'team_size_limit');
    }

    /**
     * Ensure the user can create a new workspace.
     *
     * Rules:
     * - First workspace is always allowed (can be on any plan including free)
     * - Additional workspaces require ALL existing workspaces to be on paid plans (Illuminate+)
     */
    public function ensureCanCreateWorkspace(User $user): PlanLimitResult
    {
        $ownedWorkspaces = Workspace::query()
            ->where('owner_id', $user->id)
            ->with('plan')
            ->get();

        // First workspace is always allowed
        if ($ownedWorkspaces->isEmpty()) {
            return PlanLimitResult::allow();
        }

        // Check if all existing workspaces are on paid plans (Illuminate or higher)
        foreach ($ownedWorkspaces as $workspace) {
            $plan = $workspace->plan;
            $tier = $plan !== null ? PlanTier::tryFrom($plan->tier) : PlanTier::Foundation;

            // Foundation is free (rank 1), paid plans are Illuminate+ (rank 2+)
            if ($tier === null || $tier->isFree()) {
                $message = 'To create additional workspaces, all your existing workspaces must be on a paid plan (Illuminate or higher).';

                return PlanLimitResult::deny($message, 'paid_plan_required');
            }
        }

        return PlanLimitResult::allow();
    }

    /**
     * Ensure a specific feature is enabled for the workspace's plan.
     */
    public function ensureFeatureEnabled(Workspace $workspace, PlanFeature $feature, string $message): PlanLimitResult
    {
        $plan = $this->resolvePlan($workspace);

        if ($plan->hasFeature($feature)) {
            return PlanLimitResult::allow();
        }

        $this->logLimitEvent($workspace, $feature, $message);

        return PlanLimitResult::deny($message, $feature->value);
    }

    /**
     * Get the current billing period start and end dates.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function currentPeriod(): array
    {
        $start = CarbonImmutable::now()->startOfMonth();
        $end = $start->endOfMonth();

        return [$start, $end];
    }

    /**
     * Resolve the workspace's current plan, creating a Foundation plan if none exists.
     */
    private function resolvePlan(Workspace $workspace): Plan
    {
        if ($workspace->plan !== null) {
            return $workspace->plan;
        }

        $plan = Plan::query()->firstOrCreate(
            ['tier' => PlanTier::Foundation->value],
            PlanDefaults::forTier(PlanTier::Foundation)
        );

        $workspace->forceFill([
            'plan_id' => $plan->id,
            'subscription_status' => $workspace->subscription_status ?? SubscriptionStatus::Active,
        ])->save();

        return $plan;
    }

    /**
     * Log a plan limit event to the activity log.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    private function logLimitEvent(Workspace $workspace, PlanFeature|string $limitType, string $message, ?array $metadata = null): void
    {
        $limitTypeValue = $limitType instanceof PlanFeature ? $limitType->value : $limitType;

        $this->logActivity->handle(
            workspace: $workspace,
            type: ActivityType::PlanLimitReached,
            description: $message,
            metadata: array_merge(['limit_type' => $limitTypeValue], $metadata ?? []),
        );
    }
}
