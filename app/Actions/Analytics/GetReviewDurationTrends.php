<?php

declare(strict_types=1);

namespace App\Actions\Analytics;

use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Get review duration trends over time.
 */
final readonly class GetReviewDurationTrends
{
    /**
     * Get review duration trends for a workspace.
     *
     * @return Collection<int, array{date: string, avg_duration: int, min_duration: int, max_duration: int}>
     */
    public function handle(Workspace $workspace, int $days = 30): Collection
    {
        return $workspace->runs()
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('AVG(duration_seconds) as avg_duration'),
                DB::raw('MIN(duration_seconds) as min_duration'),
                DB::raw('MAX(duration_seconds) as max_duration'),
            )
            ->whereNotNull('duration_seconds')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(static function (Run $run): array {
                /** @var string $date */
                $date = (string) $run->getAttribute('date');
                /** @var float|int|string $avgDuration */
                $avgDuration = $run->getAttribute('avg_duration');
                /** @var float|int|string $minDuration */
                $minDuration = $run->getAttribute('min_duration');
                /** @var float|int|string $maxDuration */
                $maxDuration = $run->getAttribute('max_duration');

                return [
                    'date' => $date,
                    'avg_duration' => (int) $avgDuration,
                    'min_duration' => (int) $minDuration,
                    'max_duration' => (int) $maxDuration,
                ];
            });
    }
}
