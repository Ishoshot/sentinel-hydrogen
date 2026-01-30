<?php

declare(strict_types=1);

namespace App\Actions\Analytics;

use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Get review velocity showing reviews per time period.
 */
final readonly class GetReviewVelocity
{
    /**
     * Get review velocity for a workspace.
     *
     * @return Collection<int, array{period: string, reviews_count: int, completed_count: int, avg_duration: int|null, active_repositories: int}>
     */
    public function handle(Workspace $workspace, int $days = 30, string $groupBy = 'day'): Collection
    {
        if ($groupBy === 'week') {
            $dateFormat = "DATE_TRUNC('week', created_at)";
            $groupLabel = 'week';
        } else {
            $dateFormat = 'DATE(created_at)';
            $groupLabel = 'date';
        }

        return $workspace->runs()
            ->select(
                DB::raw(sprintf('%s as %s', $dateFormat, $groupLabel)),
                DB::raw('COUNT(*) as reviews_count'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count"),
                DB::raw('AVG(duration_seconds) as avg_duration'),
                DB::raw('COUNT(DISTINCT repository_id) as active_repositories'),
            )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy($groupLabel)
            ->orderBy($groupLabel)
            ->get()
            ->map(static function (Run $run) use ($groupLabel): array {
                /** @var string $period */
                $period = (string) $run->getAttribute($groupLabel);
                /** @var int|string $reviewsCount */
                $reviewsCount = $run->getAttribute('reviews_count');
                /** @var int|string $completedCount */
                $completedCount = $run->getAttribute('completed_count');
                /** @var float|int|string|null $avgDuration */
                $avgDuration = $run->getAttribute('avg_duration');
                /** @var int|string $activeRepositories */
                $activeRepositories = $run->getAttribute('active_repositories');

                return [
                    'period' => $period,
                    'reviews_count' => (int) $reviewsCount,
                    'completed_count' => (int) $completedCount,
                    'avg_duration' => $avgDuration !== null ? (int) $avgDuration : null,
                    'active_repositories' => (int) $activeRepositories,
                ];
            });
    }
}
