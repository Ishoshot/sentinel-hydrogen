<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions;

use App\Models\Annotation;
use App\Models\Finding;
use App\Models\Run;
use App\Models\Subscription;
use App\Models\UsageRecord;
use App\Models\Workspace;
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
        [$periodStart, $periodEnd] = $this->determineBillingPeriod($workspace);

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
     * Determine the billing period for the workspace.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function determineBillingPeriod(Workspace $workspace): array
    {
        $subscription = Subscription::query()
            ->where('workspace_id', $workspace->id)
            ->latest()
            ->first();

        if ($subscription?->current_period_start !== null && $subscription?->current_period_end !== null) {
            return [
                CarbonImmutable::parse($subscription->current_period_start),
                CarbonImmutable::parse($subscription->current_period_end),
            ];
        }

        // Fall back to calendar month
        $periodStart = CarbonImmutable::now()->startOfMonth();

        return [$periodStart, $periodStart->endOfMonth()];
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
