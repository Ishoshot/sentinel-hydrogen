<?php

declare(strict_types=1);

namespace App\Jobs\Usage;

use App\Models\Annotation;
use App\Models\Finding;
use App\Models\Run;
use App\Models\UsageRecord;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;

final class AggregateUsage implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $periodStart = CarbonImmutable::now()->startOfMonth();
        $periodEnd = $periodStart->endOfMonth();

        Workspace::query()
            ->select('id')
            ->chunkById(100, function (Collection $workspaces) use ($periodStart, $periodEnd): void {
                foreach ($workspaces as $workspace) {
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
            });
    }
}
