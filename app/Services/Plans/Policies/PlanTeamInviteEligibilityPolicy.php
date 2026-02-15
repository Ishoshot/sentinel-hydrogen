<?php

declare(strict_types=1);

namespace App\Services\Plans\Policies;

use App\Models\Workspace;
use App\Services\Plans\Loggers\PlanLimitActivityLogger;
use App\Services\Plans\Resolvers\PlanResolver;
use App\Services\Plans\ValueObjects\PlanLimitResult;

final readonly class PlanTeamInviteEligibilityPolicy
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private PlanResolver $planResolver,
        private PlanLimitActivityLogger $eventLogger,
    ) {}

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
}
