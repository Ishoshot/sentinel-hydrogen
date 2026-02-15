<?php

declare(strict_types=1);

namespace App\Services\Plans\Resolvers;

use App\Enums\Commands\CommandRunStatus;
use App\Enums\Reviews\RunStatus;
use App\Models\CommandRun;
use App\Models\Run;
use App\Models\Workspace;
use App\Services\Plans\ValueObjects\BillingPeriod;

final readonly class PlanPeriodUsageResolver
{
    /**
     * Count review runs created within the billing period.
     */
    public function countRuns(Workspace $workspace, BillingPeriod $period): int
    {
        return Run::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('created_at', [$period->startAsString(), $period->endAsString()])
            ->whereIn('status', [
                RunStatus::Queued,
                RunStatus::InProgress,
                RunStatus::Completed,
                RunStatus::Failed,
            ])
            ->lockForUpdate()
            ->count();
    }

    /**
     * Count command runs created within the billing period.
     */
    public function countCommands(Workspace $workspace, BillingPeriod $period): int
    {
        return CommandRun::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('created_at', [$period->startAsString(), $period->endAsString()])
            ->whereIn('status', [
                CommandRunStatus::Queued,
                CommandRunStatus::InProgress,
                CommandRunStatus::Completed,
                CommandRunStatus::Failed,
            ])
            ->lockForUpdate()
            ->count();
    }
}
