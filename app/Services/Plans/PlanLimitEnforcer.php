<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Enums\Billing\PlanFeature;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Plans\Support\PlanLimitEventLogger;
use App\Services\Plans\Support\PlanPeriodUsageCounter;
use App\Services\Plans\Support\PlanResolver;
use App\Services\Plans\Support\PlanSubscriptionEligibilityChecker;
use App\Services\Plans\Support\PlanUsageLimitChecker;
use App\Services\Plans\Support\WorkspaceCreationEligibilityChecker;
use App\Services\Plans\ValueObjects\BillingPeriod;
use App\Services\Plans\ValueObjects\PlanLimitResult;

final readonly class PlanLimitEnforcer
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private PlanResolver $planResolver,
        private PlanSubscriptionEligibilityChecker $subscriptionEligibilityChecker,
        private PlanPeriodUsageCounter $periodUsageCounter,
        private WorkspaceCreationEligibilityChecker $workspaceCreationEligibilityChecker,
        private PlanLimitEventLogger $eventLogger,
        private PlanUsageLimitChecker $usageLimitChecker,
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
        return $this->subscriptionEligibilityChecker->ensureActiveSubscription($workspace);
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

        $plan = $this->planResolver->resolve($workspace);
        $period = $this->currentPeriod($workspace);
        $runsCount = $this->periodUsageCounter->countRuns($workspace, $period);

        return $this->usageLimitChecker->check(
            $workspace,
            $plan->monthly_runs_limit,
            $runsCount,
            'runs_limit',
            'Run limit reached (%d/%d). Upgrade your plan to run more reviews.',
            ['runs_count' => $runsCount],
        );
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

        $plan = $this->planResolver->resolve($workspace);
        $period = $this->currentPeriod($workspace);
        $commandsCount = $this->periodUsageCounter->countCommands($workspace, $period);

        return $this->usageLimitChecker->check(
            $workspace,
            $plan->monthly_commands_limit,
            $commandsCount,
            'commands_limit',
            'Command limit reached (%d/%d). Upgrade your plan to run more commands.',
            ['commands_count' => $commandsCount],
        );
    }

    /**
     * Ensure the workspace can invite a new team member.
     */
    public function ensureCanInviteMember(Workspace $workspace): PlanLimitResult
    {
        $plan = $this->planResolver->resolve($workspace);
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

        $this->eventLogger->log($workspace, 'team_size_limit', $message, [
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
        return $this->workspaceCreationEligibilityChecker->ensureCanCreate($user);
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

        $this->eventLogger->log($workspace, $feature, $message);

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
