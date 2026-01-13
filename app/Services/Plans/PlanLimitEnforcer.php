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
