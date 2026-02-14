<?php

declare(strict_types=1);

namespace App\Services\Briefings;

use App\Enums\Reviews\RunStatus;
use App\Models\Run;
use App\Services\Briefings\ValueObjects\BriefingDateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use stdClass;

/**
 * Provides reusable run metrics queries for briefing generation.
 */
final class BriefingRunMetricsService
{
    private const int CACHE_TTL_SECONDS = 60;

    /**
     * Build a scoped runs query builder for a workspace, date range, and optional repository filter.
     *
     * @param  array<int, int>  $repositoryIds
     * @return Builder<Run>
     */
    public function buildRunsQuery(int $workspaceId, BriefingDateRange $dateRange, array $repositoryIds = []): Builder
    {
        $query = Run::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('created_at', [$dateRange->start, $dateRange->end]);

        if ($repositoryIds !== []) {
            $query->whereIn('repository_id', $repositoryIds);
        }

        return $query;
    }

    /**
     * Fetch run summary aggregates (counts by status, active days) in a single query.
     *
     * @param  Builder<Run>  $runsQuery
     */
    public function fetchRunSummary(Builder $runsQuery): stdClass
    {
        /** @var \Illuminate\Database\Connection $connection */
        $connection = $runsQuery->getQuery()->getConnection();
        $driver = $connection->getDriverName();
        $activeDaysExpression = $driver === 'pgsql'
            ? 'COUNT(DISTINCT created_at::date) as active_days'
            : 'COUNT(DISTINCT DATE(created_at)) as active_days';

        /** @var stdClass */
        return (clone $runsQuery)->toBase()
            ->selectRaw('COUNT(*) as total_runs')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed', [RunStatus::Completed->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress', [RunStatus::InProgress->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed', [RunStatus::Failed->value])
            ->selectRaw($activeDaysExpression)
            ->first();
    }

    /**
     * Calculate review coverage percentage for a workspace and date range.
     *
     * @param  array<int, int>  $repositoryIds
     */
    public function calculateReviewCoverage(int $workspaceId, BriefingDateRange $dateRange, array $repositoryIds = []): float
    {
        $cacheKey = sprintf(
            'briefing:review_coverage:%d:%s:%s:%s',
            $workspaceId,
            $dateRange->start->toDateString(),
            $dateRange->end->toDateString(),
            implode(',', $repositoryIds),
        );

        /** @var float $coverage */
        $coverage = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($workspaceId, $dateRange, $repositoryIds): float {
            $coverageQuery = Run::query()
                ->where('workspace_id', $workspaceId)
                ->whereNotNull('pr_number')
                ->whereBetween('completed_at', [$dateRange->start, $dateRange->end])
                ->where('status', '!=', RunStatus::Skipped->value);

            if ($repositoryIds !== []) {
                $coverageQuery->whereIn('repository_id', $repositoryIds);
            }

            /** @var stdClass $coverageRow */
            $coverageRow = $coverageQuery->toBase()
                ->selectRaw("COUNT(DISTINCT CONCAT(repository_id, ':', pr_number)) as eligible_count")
                ->selectRaw("COUNT(DISTINCT CASE WHEN status = ? THEN CONCAT(repository_id, ':', pr_number) END) as completed_count", [RunStatus::Completed->value])
                ->first();

            $eligibleCount = (int) ($coverageRow->eligible_count ?? 0);

            if ($eligibleCount === 0) {
                return 0.0;
            }

            $completedCount = (int) ($coverageRow->completed_count ?? 0);

            return round(($completedCount / $eligibleCount) * 100, 1);
        });

        return $coverage;
    }
}
