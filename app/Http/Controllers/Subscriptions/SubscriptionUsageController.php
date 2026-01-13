<?php

declare(strict_types=1);

namespace App\Http\Controllers\Subscriptions;

use App\Http\Resources\UsageResource;
use App\Models\Annotation;
use App\Models\Finding;
use App\Models\Run;
use App\Models\UsageRecord;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Display usage statistics for the current billing period.
 */
final class SubscriptionUsageController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Workspace $workspace): JsonResponse
    {
        Gate::authorize('view', $workspace);

        $periodStart = CarbonImmutable::now()->startOfMonth();
        $periodEnd = $periodStart->endOfMonth();

        $usage = UsageRecord::query()
            ->where('workspace_id', $workspace->id)
            ->forPeriod($periodStart, $periodEnd)
            ->first();

        if ($usage === null) {
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

            $usage = UsageRecord::create([
                'workspace_id' => $workspace->id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'runs_count' => $runsCount,
                'findings_count' => $findingsCount,
                'annotations_count' => $annotationsCount,
            ]);
        }

        return response()->json([
            'data' => new UsageResource($usage),
        ]);
    }
}
