<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions;

use App\Models\Annotation;
use App\Models\Finding;
use App\Models\Run;
use App\Models\UsageRecord;
use App\Models\Workspace;
use App\Services\Plans\ValueObjects\BillingPeriod;
use Illuminate\Database\Eloquent\Collection;

/**
 * Aggregate usage counters for all workspaces in their active billing periods.
 */
final class AggregateWorkspaceUsage
{
    /**
     * Aggregate and persist usage records for all workspaces.
     */
    public function handle(): void
    {
        Workspace::query()
            ->select('id')
            ->chunkById(100, function (Collection $workspaces): void {
                foreach ($workspaces as $workspace) {
                    $this->aggregateWorkspace($workspace);
                }
            });
    }

    /**
     * Aggregate usage counters for a single workspace.
     */
    private function aggregateWorkspace(Workspace $workspace): void
    {
        $period = BillingPeriod::forWorkspace($workspace);
        $periodStart = $period->start;
        $periodEnd = $period->end;
        $periodBounds = [$periodStart->startOfDay(), $periodEnd->endOfDay()];

        $runsCount = Run::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('created_at', $periodBounds)
            ->count();

        $findingsCount = Finding::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('created_at', $periodBounds)
            ->count();

        $annotationsCount = Annotation::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('created_at', $periodBounds)
            ->count();

        UsageRecord::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
            ],
            [
                'runs_count' => $runsCount,
                'findings_count' => $findingsCount,
                'annotations_count' => $annotationsCount,
            ]
        );
    }
}
