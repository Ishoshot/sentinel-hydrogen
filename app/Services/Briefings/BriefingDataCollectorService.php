<?php

declare(strict_types=1);

namespace App\Services\Briefings;

use App\Enums\Reviews\FindingCategory;
use App\Enums\Reviews\RunStatus;
use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Models\Finding;
use App\Models\Repository;
use App\Models\Run;
use App\Services\Briefings\Contracts\BriefingDataCollector;
use App\Services\Briefings\ValueObjects\Achievement;
use App\Services\Briefings\ValueObjects\BriefingAchievements;
use App\Services\Briefings\ValueObjects\BriefingDataQuality;
use App\Services\Briefings\ValueObjects\BriefingDateRange;
use App\Services\Briefings\ValueObjects\BriefingEvidence;
use App\Services\Briefings\ValueObjects\BriefingParameters;
use App\Services\Briefings\ValueObjects\BriefingStructuredData;
use App\Services\Briefings\ValueObjects\BriefingSummary;
use App\Services\Briefings\ValueObjects\BriefingTopContributor;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class BriefingDataCollectorService implements BriefingDataCollector
{
    private const int RUN_ACTIVITY_LIMIT = 20;

    private const int ENGINEER_LIMIT = 10;

    private const int CRITICAL_FINDINGS_LIMIT = 10;

    private const int REPOSITORY_LIST_LIMIT = 25;

    private const int DATA_SPARSE_RUN_THRESHOLD = 5;

    /**
     * Collect data for a briefing.
     *
     * @param  int  $workspaceId  The workspace ID to collect data for
     * @param  string  $briefingSlug  The briefing template slug
     * @param  BriefingParameters  $parameters  User-provided parameters
     * @return BriefingStructuredData The collected structured data
     */
    public function collect(int $workspaceId, string $briefingSlug, BriefingParameters $parameters): BriefingStructuredData
    {
        $parameterValues = $parameters->toArray();
        $dateRange = $this->resolveDateRange($parameterValues);

        $data = match ($briefingSlug) {
            'standup-update' => $this->collectStandupData($workspaceId, $dateRange, $parameterValues),
            'weekly-team-summary' => $this->collectTeamSummaryData($workspaceId, $dateRange, $parameterValues),
            'delivery-velocity' => $this->collectVelocityData($workspaceId, $dateRange, $parameterValues),
            'engineer-spotlight' => $this->collectEngineerSpotlightData($workspaceId, $dateRange, $parameterValues),
            'company-update' => $this->collectCompanyUpdateData($workspaceId, $dateRange, $parameterValues),
            'sprint-retrospective' => $this->collectRetrospectiveData($workspaceId, $dateRange, $parameterValues),
            'code-health' => $this->collectCodeHealthData($workspaceId, $dateRange, $parameterValues),
            default => throw new RuntimeException(sprintf('Unsupported briefing slug: %s', $briefingSlug)),
        };

        return BriefingStructuredData::fromArray($data);
    }

    /**
     * Detect achievement milestones based on structured data.
     *
     * @param  BriefingStructuredData  $structuredData  The structured data to evaluate
     * @return BriefingAchievements The achievements found for the period
     */
    public function detectAchievements(BriefingStructuredData $structuredData): BriefingAchievements
    {
        /** @var array<int, Achievement> $achievements */
        $achievements = [];
        $summary = $structuredData->summary();

        $this->detectPrMilestone($achievements, $summary->prsMerged());
        $this->detectReviewCoverage($achievements, $summary);
        $this->detectActivityStreak($achievements, $summary->activeDays());
        $this->detectTopContributor($achievements, $structuredData->topContributor());

        return BriefingAchievements::fromItems($achievements);
    }

    /**
     * @param  array<int, Achievement>  $achievements
     */
    private function detectPrMilestone(array &$achievements, int $prCount): void
    {
        $milestone = match (true) {
            $prCount >= 100 => ['title' => 'Century Club', 'description' => sprintf('%d pull requests merged!', $prCount)],
            $prCount >= 50 => ['title' => 'Halfway There', 'description' => sprintf('%d pull requests merged this period', $prCount)],
            default => null,
        };

        if ($milestone !== null) {
            $achievements[] = new Achievement(
                type: 'milestone',
                title: $milestone['title'],
                description: $milestone['description'],
                value: $prCount,
            );
        }
    }

    /**
     * @param  array<int, Achievement>  $achievements
     */
    private function detectReviewCoverage(array &$achievements, BriefingSummary $summary): void
    {
        $reviewCoverage = $summary->reviewCoverage();

        if ($reviewCoverage >= 95) {
            $achievements[] = new Achievement(
                type: 'milestone',
                title: 'Full Coverage',
                description: sprintf('%.1f%% of PRs reviewed by AI', $reviewCoverage),
                value: $reviewCoverage,
            );
        }
    }

    /**
     * @param  array<int, Achievement>  $achievements
     */
    private function detectActivityStreak(array &$achievements, int $activeDays): void
    {
        $streak = match (true) {
            $activeDays >= 14 => ['title' => 'Two Week Streak', 'description' => sprintf('%d consecutive days of activity', $activeDays)],
            $activeDays >= 7 => ['title' => 'Week Warrior', 'description' => sprintf('%d consecutive days of activity', $activeDays)],
            default => null,
        };

        if ($streak !== null) {
            $achievements[] = new Achievement(
                type: 'streak',
                title: $streak['title'],
                description: $streak['description'],
                value: $activeDays,
            );
        }
    }

    /**
     * @param  array<int, Achievement>  $achievements
     */
    private function detectTopContributor(array &$achievements, ?BriefingTopContributor $topContributor): void
    {
        if (! $topContributor instanceof BriefingTopContributor) {
            return;
        }

        $prCount = $topContributor->prCount;

        if ($prCount >= 10) {
            $achievements[] = new Achievement(
                type: 'personal_best',
                title: 'Star Performer',
                description: sprintf('%s merged %d PRs this period', $topContributor->name, $prCount),
                value: $prCount,
            );
        }
    }

    /**
     * Resolve the date range from parameters.
     *
     * @param  array<string, mixed>  $parameters  The parameters
     * @return BriefingDateRange The date range
     */
    private function resolveDateRange(array $parameters): BriefingDateRange
    {
        return BriefingDateRange::fromArray($parameters);
    }

    /**
     * @param  array<int, int>  $repositoryIds
     */
    private function calculateReviewCoverage(int $workspaceId, BriefingDateRange $dateRange, array $repositoryIds = []): float
    {
        $coverageQuery = Run::query()
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('pr_number')
            ->whereBetween('completed_at', [$dateRange->start, $dateRange->end])
            ->where('status', '!=', RunStatus::Skipped->value);

        if ($repositoryIds !== []) {
            $coverageQuery->whereIn('repository_id', $repositoryIds);
        }

        $eligibleCount = (int) (clone $coverageQuery)
            ->selectRaw("COUNT(DISTINCT CONCAT(repository_id, ':', pr_number)) as count")
            ->value('count');

        if ($eligibleCount === 0) {
            return 0.0;
        }

        $completedCount = (int) (clone $coverageQuery)
            ->where('status', RunStatus::Completed->value)
            ->selectRaw("COUNT(DISTINCT CONCAT(repository_id, ':', pr_number)) as count")
            ->value('count');

        return round(($completedCount / $eligibleCount) * 100, 1);
    }

    /**
     * Collect data for daily standup briefing.
     *
     * @param  int  $workspaceId  The workspace ID
     * @param  BriefingDateRange  $dateRange  The date range
     * @param  array<string, mixed>  $parameters  The parameters
     * @return array<string, mixed> The collected data
     */
    private function collectStandupData(int $workspaceId, BriefingDateRange $dateRange, array $parameters): array
    {
        $repositoryIds = $parameters['repository_ids'] ?? [];

        $runsQuery = Run::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('created_at', [$dateRange->start, $dateRange->end]);

        if (! empty($repositoryIds)) {
            $runsQuery->whereIn('repository_id', $repositoryIds);
        }

        $summaryRow = (clone $runsQuery)
            ->selectRaw('COUNT(*) as total_runs')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed', [RunStatus::Completed->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress', [RunStatus::InProgress->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed', [RunStatus::Failed->value])
            ->first();

        $totalRuns = (int) ($summaryRow->total_runs ?? 0);
        $completedRuns = (int) ($summaryRow->completed ?? 0);
        $inProgressRuns = (int) ($summaryRow->in_progress ?? 0);
        $failedRuns = (int) ($summaryRow->failed ?? 0);

        $activeDays = (int) (clone $runsQuery)
            ->selectRaw('COUNT(DISTINCT DATE(created_at)) as active_days')
            ->value('active_days');

        $reviewCoverage = $this->calculateReviewCoverage($workspaceId, $dateRange, $repositoryIds);

        $recentRuns = (clone $runsQuery)
            ->orderByDesc('created_at')
            ->limit(self::RUN_ACTIVITY_LIMIT)
            ->get();

        $periodDays = $dateRange->days();
        $dataQuality = $this->buildDataQuality(
            totalRuns: $totalRuns,
            activeDays: $activeDays,
            periodDays: $periodDays,
            reviewCoverage: $reviewCoverage,
        );

        $runIds = $recentRuns
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        $evidence = $this->buildEvidence(runIds: $runIds);

        $summary = BriefingSummary::fromArray([
            'total_runs' => $totalRuns,
            'completed' => $completedRuns,
            'in_progress' => $inProgressRuns,
            'failed' => $failedRuns,
            'prs_merged' => $completedRuns,
            'active_days' => $activeDays,
            'review_coverage' => $reviewCoverage,
        ]);

        return [
            'period' => $dateRange->toPeriod()->toArray(),
            'summary' => $summary->toArray(),
            'runs' => $recentRuns->map(fn (Run $run): array => [
                'id' => $run->id,
                'pr_number' => $run->pr_number,
                'pr_title' => $run->pr_title,
                'status' => $run->status->value,
                'created_at' => $run->created_at?->toIso8601String(),
            ])->values()->all(),
            'data_quality' => $dataQuality->toArray(),
            'evidence' => $evidence->toArray(),
        ];
    }

    /**
     * Collect data for weekly team summary briefing.
     *
     * @param  int  $workspaceId  The workspace ID
     * @param  BriefingDateRange  $dateRange  The date range
     * @param  array<string, mixed>  $parameters  The parameters
     * @return array<string, mixed> The collected data
     */
    private function collectTeamSummaryData(int $workspaceId, BriefingDateRange $dateRange, array $parameters): array
    {
        $data = $this->collectStandupData($workspaceId, $dateRange, $parameters);

        $repositoryIds = $parameters['repository_ids'] ?? [];

        $repositoriesQuery = Repository::query()
            ->where('workspace_id', $workspaceId);

        if (! empty($repositoryIds)) {
            $repositoriesQuery->whereIn('id', $repositoryIds);
        }

        $repositoryCount = (int) (clone $repositoriesQuery)->count();

        $repositories = (clone $repositoriesQuery)
            ->orderBy('id')
            ->limit(self::REPOSITORY_LIST_LIMIT)
            ->get(['id', 'name', 'full_name']);

        $data['repositories'] = $repositories->map(fn (Repository $repo): array => [
            'id' => $repo->id,
            'name' => $repo->name,
            'full_name' => $repo->full_name,
        ])->values()->all();

        /** @var array<string, mixed> $summaryPayload */
        $summaryPayload = is_array($data['summary'] ?? null) ? $data['summary'] : [];
        $summaryPayload['repository_count'] = $repositoryCount;
        $summary = BriefingSummary::fromArray($summaryPayload);

        $periodDays = $dateRange->days();

        $dataQuality = $this->buildDataQuality(
            totalRuns: $summary->totalRuns(),
            activeDays: $summary->activeDays(),
            periodDays: $periodDays,
            reviewCoverage: $summary->reviewCoverage(),
            repositoryCount: $repositoryCount,
        );

        /** @var array<string, mixed> $evidencePayload */
        $evidencePayload = is_array($data['evidence'] ?? null) ? $data['evidence'] : [];
        $existingEvidence = BriefingEvidence::fromArray($evidencePayload);

        $evidence = $this->buildEvidence(
            runIds: $existingEvidence->runIds,
            repositoryNames: $repositories->map(
                fn (Repository $repo): string => (string) ($repo->full_name ?? $repo->name ?? '')
            )->filter()->values()->all(),
        );

        $data['summary'] = $summary->toArray();
        $data['data_quality'] = $dataQuality->toArray();
        $data['evidence'] = $evidence->toArray();

        return $data;
    }

    /**
     * Collect data for delivery velocity briefing.
     *
     * @param  int  $workspaceId  The workspace ID
     * @param  BriefingDateRange  $dateRange  The date range
     * @param  array<string, mixed>  $parameters  The parameters
     * @return array<string, mixed> The collected data
     */
    private function collectVelocityData(int $workspaceId, BriefingDateRange $dateRange, array $parameters): array
    {
        $data = $this->collectStandupData($workspaceId, $dateRange, $parameters);

        // Calculate velocity metrics
        $totalDays = $dateRange->days();
        /** @var array<string, mixed> $summaryPayload */
        $summaryPayload = is_array($data['summary'] ?? null) ? $data['summary'] : [];
        $summary = BriefingSummary::fromArray($summaryPayload);
        $prsCompleted = $summary->completed();

        $data['velocity'] = [
            'prs_per_day' => $totalDays > 0 ? round($prsCompleted / $totalDays, 2) : 0,
            'total_days' => $totalDays,
        ];

        return $data;
    }

    /**
     * Collect data for engineer spotlight briefing.
     *
     * @param  int  $workspaceId  The workspace ID
     * @param  BriefingDateRange  $dateRange  The date range
     * @param  array<string, mixed>  $parameters  The parameters
     * @return array<string, mixed> The collected data
     */
    private function collectEngineerSpotlightData(int $workspaceId, BriefingDateRange $dateRange, array $parameters): array
    {
        $data = $this->collectStandupData($workspaceId, $dateRange, $parameters);

        $repositoryIds = $parameters['repository_ids'] ?? [];

        // Get contributor statistics from run metadata (author or author_login)
        $authorExpression = "COALESCE(metadata->>'author', metadata->>'author_login')";

        $contributorsQuery = Run::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('created_at', [$dateRange->start, $dateRange->end])
            ->whereRaw($authorExpression.' is not null')
            ->selectRaw($authorExpression.' as author')
            ->selectRaw('COUNT(*) as pr_count')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed', [RunStatus::Completed->value])
            ->groupBy('author')
            ->orderByDesc('pr_count')
            ->limit(self::ENGINEER_LIMIT);

        if (! empty($repositoryIds)) {
            $contributorsQuery->whereIn('repository_id', $repositoryIds);
        }

        $contributors = $contributorsQuery->get();

        $engineers = $contributors->map(fn (Run $run): array => [
            'name' => (string) $run->getAttribute('author'),
            'pr_count' => (int) $run->getAttribute('pr_count'),
            'completed' => (int) $run->getAttribute('completed'),
        ])->values()->all();

        $data['engineers'] = $engineers;
        $data['top_contributor'] = $engineers === []
            ? null
            : BriefingTopContributor::fromArray($engineers[0])?->toArray();

        return $data;
    }

    /**
     * Collect data for company update briefing.
     *
     * @param  int  $workspaceId  The workspace ID
     * @param  BriefingDateRange  $dateRange  The date range
     * @param  array<string, mixed>  $parameters  The parameters
     * @return array<string, mixed> The collected data
     */
    private function collectCompanyUpdateData(int $workspaceId, BriefingDateRange $dateRange, array $parameters): array
    {
        return $this->collectTeamSummaryData($workspaceId, $dateRange, $parameters);
    }

    /**
     * Collect data for sprint retrospective briefing.
     *
     * @param  int  $workspaceId  The workspace ID
     * @param  BriefingDateRange  $dateRange  The date range
     * @param  array<string, mixed>  $parameters  The parameters
     * @return array<string, mixed> The collected data
     */
    private function collectRetrospectiveData(int $workspaceId, BriefingDateRange $dateRange, array $parameters): array
    {
        $data = $this->collectTeamSummaryData($workspaceId, $dateRange, $parameters);

        // Add sprint-specific metrics
        $data['retrospective'] = [
            'sprint_goal' => $parameters['sprint_goal'] ?? null,
            'sprint_number' => $parameters['sprint_number'] ?? null,
        ];

        return $data;
    }

    /**
     * Collect data for code health briefing.
     *
     * @param  int  $workspaceId  The workspace ID
     * @param  BriefingDateRange  $dateRange  The date range
     * @param  array<string, mixed>  $parameters  The parameters
     * @return array<string, mixed> The collected data
     */
    private function collectCodeHealthData(int $workspaceId, BriefingDateRange $dateRange, array $parameters): array
    {
        $data = $this->collectStandupData($workspaceId, $dateRange, $parameters);

        $repositoryIds = $parameters['repository_ids'] ?? [];

        // Query findings within the date range
        $findingsQuery = Finding::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('created_at', [$dateRange->start, $dateRange->end]);

        if (! empty($repositoryIds)) {
            $findingsQuery->whereHas('run', function (\Illuminate\Database\Eloquent\Builder $query) use ($repositoryIds): void {
                $query->whereIn('repository_id', $repositoryIds);
            });
        }

        $totalFindings = (int) (clone $findingsQuery)->count();

        // Count by severity
        $severityCounts = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'info' => 0,
        ];

        $severityRows = (clone $findingsQuery)
            ->select('severity', DB::raw('COUNT(*) as count'))
            ->groupBy('severity')
            ->get();

        foreach ($severityRows as $row) {
            $severity = $row->severity instanceof SentinelConfigSeverity
                ? $row->severity->value
                : (string) $row->severity;
            if (array_key_exists($severity, $severityCounts)) {
                $severityCounts[$severity] = (int) $row->getAttribute('count');
            }
        }

        // Count by category
        $categoryCounts = [];
        foreach (FindingCategory::cases() as $category) {
            $categoryCounts[$category->value] = 0;
        }

        $categoryRows = (clone $findingsQuery)
            ->select('category', DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->get();

        foreach ($categoryRows as $row) {
            $category = $row->category instanceof FindingCategory
                ? $row->category->value
                : (string) $row->category;
            if ($category !== '' && isset($categoryCounts[$category])) {
                $categoryCounts[$category] = (int) $row->getAttribute('count');
            }
        }

        // Get top issues by severity (critical and high)
        $criticalFindings = (clone $findingsQuery)
            ->whereIn('severity', [
                SentinelConfigSeverity::Critical->value,
                SentinelConfigSeverity::High->value,
            ])
            ->orderByRaw('CASE WHEN severity = ? THEN 0 ELSE 1 END', [SentinelConfigSeverity::Critical->value])
            ->orderByDesc('created_at')
            ->limit(self::CRITICAL_FINDINGS_LIMIT)
            ->get(['id', 'title', 'severity', 'category', 'file_path', 'line_start'])
            ->map(fn (Finding $f): array => [
                'id' => $f->id,
                'title' => $f->title,
                'severity' => $f->severity?->value,
                'category' => $f->category?->value,
                'file_path' => $f->file_path,
                'line_start' => $f->line_start,
            ])->values()->all();

        $data['code_health'] = [
            'total_findings' => $totalFindings,
            'critical_issues' => $severityCounts['critical'],
            'high_issues' => $severityCounts['high'],
            'medium_issues' => $severityCounts['medium'],
            'low_issues' => $severityCounts['low'],
            'info_issues' => $severityCounts['info'],
            'severity_breakdown' => $severityCounts,
            'category_breakdown' => $categoryCounts,
            'top_critical_findings' => $criticalFindings,
        ];

        $evidence = $this->buildEvidence(
            runIds: $data['evidence']['run_ids'] ?? [],
            findingIds: array_map(
                static fn (mixed $id): int => (int) $id,
                array_column($criticalFindings, 'id')
            ),
        );

        $data['evidence'] = $evidence->toArray();

        return $data;
    }

    /**
     * Build a data quality summary for the briefing.
     */
    private function buildDataQuality(
        int $totalRuns,
        int $activeDays,
        int $periodDays,
        float $reviewCoverage,
        int $repositoryCount = 0,
    ): BriefingDataQuality {
        $notes = [];

        if ($totalRuns === 0) {
            $notes[] = 'No runs recorded for this period.';
        }

        if ($totalRuns > 0 && $activeDays <= 1) {
            $notes[] = 'Activity occurred on a single day or fewer.';
        }

        if ($repositoryCount === 0) {
            $notes[] = 'No repositories matched the selected filters.';
        }

        if ($totalRuns > 0 && $reviewCoverage === 0.0) {
            $notes[] = 'Review coverage data is unavailable for this period.';
        }

        $isSparse = $totalRuns < self::DATA_SPARSE_RUN_THRESHOLD;

        return new BriefingDataQuality(
            isSparse: $isSparse,
            totalRuns: $totalRuns,
            activeDays: $activeDays,
            periodDays: $periodDays,
            reviewCoverage: $reviewCoverage,
            notes: $notes,
        );
    }

    /**
     * @param  array<int, int>  $runIds
     * @param  array<int, int>  $findingIds
     * @param  array<int, string>  $repositoryNames
     */
    private function buildEvidence(
        array $runIds = [],
        array $findingIds = [],
        array $repositoryNames = [],
    ): BriefingEvidence {
        return new BriefingEvidence(
            runIds: array_values(array_unique(array_map(intval(...), $runIds))),
            findingIds: array_values(array_unique(array_map(intval(...), $findingIds))),
            repositoryNames: array_values(array_unique(array_filter($repositoryNames))),
        );
    }
}
