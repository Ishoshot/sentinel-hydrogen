<?php

declare(strict_types=1);

namespace App\Actions\Analytics;

use App\Enums\Reviews\RunStatus;
use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Support\Collection;

/**
 * Get success vs failure rate for runs.
 */
final readonly class GetSuccessRate
{
    /**
     * Get success rate for a workspace.
     *
     * @return Collection<int, array{date: string, successful: int, failed: int, total: int, success_rate: float}>
     */
    public function handle(Workspace $workspace, int $days = 30): Collection
    {
        $runs = $workspace->runs()
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        return $runs
            ->groupBy(static fn (Run $run): string => $run->created_at->toDateString())
            ->map(static function (Collection $dayRuns, string $date): array {
                /** @var Collection<int, Run> $dayRuns */
                $successful = $dayRuns->filter(static fn (Run $run): bool => $run->status === RunStatus::Completed)->count();
                $failed = $dayRuns->filter(static fn (Run $run): bool => $run->status === RunStatus::Failed)->count();
                $total = $dayRuns->count();
                $successRate = $total > 0 ? round(($successful / $total) * 100, 2) : 0;

                return [
                    'date' => $date,
                    'successful' => $successful,
                    'failed' => $failed,
                    'total' => $total,
                    'success_rate' => $successRate,
                ];
            })
            ->sortBy('date')
            ->values();
    }
}
