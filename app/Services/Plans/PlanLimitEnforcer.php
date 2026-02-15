<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Enums\Billing\PlanFeature;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Plans\Loggers\PlanLimitActivityLogger;
use App\Services\Plans\Policies\PlanMeteredUsagePolicy;
use App\Services\Plans\Policies\PlanSubscriptionEligibilityPolicy;
use App\Services\Plans\Policies\PlanTeamInviteEligibilityPolicy;
use App\Services\Plans\Policies\WorkspaceCreationEligibilityPolicy;
use App\Services\Plans\Resolvers\PlanResolver;
use App\Services\Plans\ValueObjects\BillingPeriod;
use App\Services\Plans\ValueObjects\PlanLimitResult;

final readonly class PlanLimitEnforcer
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private PlanResolver $planResolver,
        private PlanSubscriptionEligibilityPolicy $subscriptionEligibilityPolicy,
        private WorkspaceCreationEligibilityPolicy $workspaceCreationEligibilityPolicy,
        private PlanLimitActivityLogger $activityLogger,
        private PlanMeteredUsagePolicy $meteredUsagePolicy,
        private PlanTeamInviteEligibilityPolicy $teamInviteEligibilityPolicy,
    ) {}

    /**
     * Ensure the workspace has an active subscription.
     *
     * Grants access if the workspace status is Active/Trialing, or if the
     * status is Canceled but the latest subscription's ends_at is still
     * in the future (grace period until the billing period ends).
     */
    public function ensureActiveSubscription(Workspace $workspace): PlanLimitResult
    {
        return $this->subscriptionEligibilityPolicy->ensureActiveSubscription($workspace);
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

        return $this->meteredUsagePolicy->ensureRunAllowed($workspace);
    }

    /**
     * Ensure the workspace can execute a new command within its plan limits.
     */
    public function ensureCommandAllowed(Workspace $workspace): PlanLimitResult
    {
        $activeCheck = $this->ensureActiveSubscription($workspace);

        if (! $activeCheck->allowed) {
            return $activeCheck;
        }

        return $this->meteredUsagePolicy->ensureCommandAllowed($workspace);
    }

    /**
     * Ensure the workspace can invite a new team member.
     */
    public function ensureCanInviteMember(Workspace $workspace): PlanLimitResult
    {
        return $this->teamInviteEligibilityPolicy->ensureCanInviteMember($workspace);
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
        return $this->workspaceCreationEligibilityPolicy->ensureCanCreate($user);
    }

    /**
     * Ensure a specific feature is enabled for the workspace's plan.
     */
    public function ensureFeatureEnabled(Workspace $workspace, PlanFeature $feature, string $message): PlanLimitResult
    {
        $plan = $this->planResolver->resolve($workspace);

        if ($plan->hasFeature($feature)) {
            return PlanLimitResult::allow();
        }

        $this->activityLogger->log($workspace, $feature, $message);

        return PlanLimitResult::deny($message, $feature->value);
    }

    /**
     * Get the current billing period for a workspace.
     */
    public function currentPeriod(Workspace $workspace): BillingPeriod
    {
        return BillingPeriod::forWorkspace($workspace);
    }
}
