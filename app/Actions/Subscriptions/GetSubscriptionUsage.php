<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions;

use App\Models\Annotation;
use App\Models\Finding;
use App\Models\Run;
use App\Models\UsageRecord;
use App\Models\Workspace;
use App\Services\Plans\ValueObjects\BillingPeriod;
use Carbon\CarbonImmutable;

/**
 * Get or create usage statistics for the current billing period.
 */
final class GetSubscriptionUsage
{
    /**
     * Get usage statistics for the workspace's current billing period.
     */
    public function handle(Workspace $workspace): UsageRecord
    {
        $period = BillingPeriod::forWorkspace($workspace);
        $periodStart = $period->start;
        $periodEnd = $period->end;

        $usage = UsageRecord::query()
            ->where('workspace_id', $workspace->id)
            ->forPeriod($periodStart, $periodEnd)
            ->first();

        if ($usage !== null) {
            return $usage;
        }

        return $this->createUsageRecord($workspace, $periodStart, $periodEnd);
    }

    /**
     * Create a new usage record for the period.
     */
    private function createUsageRecord(
        Workspace $workspace,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
    ): UsageRecord {
        $runsCount = Run::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('created_at', [$periodStart->startOfDay(), $periodEnd->endOfDay()])
            ->count();

        $findingsCount = Finding::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('created_at', [$periodStart->startOfDay(), $periodEnd->endOfDay()])
            ->count();

        $annotationsCount = Annotation::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('created_at', [$periodStart->startOfDay(), $periodEnd->endOfDay()])
            ->count();

        return UsageRecord::create([
            'workspace_id' => $workspace->id,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'runs_count' => $runsCount,
            'findings_count' => $findingsCount,
            'annotations_count' => $annotationsCount,
        ]);
    }
}
