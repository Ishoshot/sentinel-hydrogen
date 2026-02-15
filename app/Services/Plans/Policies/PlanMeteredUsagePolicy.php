<?php

declare(strict_types=1);

namespace App\Services\Plans\Policies;

use App\Models\Workspace;
use App\Services\Plans\Loggers\PlanLimitActivityLogger;
use App\Services\Plans\Resolvers\PlanPeriodUsageResolver;
use App\Services\Plans\Resolvers\PlanResolver;
use App\Services\Plans\ValueObjects\BillingPeriod;
use App\Services\Plans\ValueObjects\PlanLimitResult;

final readonly class PlanMeteredUsagePolicy
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private PlanResolver $planResolver,
        private PlanPeriodUsageResolver $periodUsageResolver,
        private PlanLimitActivityLogger $activityLogger,
    ) {}

    /**
     * Ensure review runs are within the workspace plan limit.
     */
    public function ensureRunAllowed(Workspace $workspace): PlanLimitResult
    {
        $plan = $this->planResolver->resolve($workspace);
        $period = BillingPeriod::forWorkspace($workspace);
        $runsCount = $this->periodUsageResolver->countRuns($workspace, $period);

        return $this->enforceUsageLimit(
            $workspace,
            $plan->monthly_runs_limit,
            $runsCount,
            'runs_limit',
            'Run limit reached (%d/%d). Upgrade your plan to run more reviews.',
            ['runs_count' => $runsCount],
        );
    }

    /**
     * Ensure command runs are within the workspace plan limit.
     */
    public function ensureCommandAllowed(Workspace $workspace): PlanLimitResult
    {
        $plan = $this->planResolver->resolve($workspace);
        $period = BillingPeriod::forWorkspace($workspace);
        $commandsCount = $this->periodUsageResolver->countCommands($workspace, $period);

        return $this->enforceUsageLimit(
            $workspace,
            $plan->monthly_commands_limit,
            $commandsCount,
            'commands_limit',
            'Command limit reached (%d/%d). Upgrade your plan to run more commands.',
            ['commands_count' => $commandsCount],
        );
    }

    /**
     * @param  array<string, int>  $metadata
     */
    private function enforceUsageLimit(
        Workspace $workspace,
        ?int $limit,
        int $usage,
        string $limitName,
        string $messageFormat,
        array $metadata = [],
    ): PlanLimitResult {
        if ($limit === null) {
            return PlanLimitResult::allow();
        }

        if ($usage < $limit) {
            return PlanLimitResult::allow();
        }

        $message = sprintf($messageFormat, $usage, $limit);

        $this->activityLogger->log($workspace, $limitName, $message, array_merge(
            $metadata,
            ['limit' => $limit],
        ));

        return PlanLimitResult::deny($message, $limitName);
    }
}
