<?php

declare(strict_types=1);

namespace App\Services\Briefings;

use App\Enums\FindingCategory;
use App\Enums\SentinelConfigSeverity;
use App\Models\Finding;
use App\Models\Repository;
use App\Models\Run;
use App\Services\Briefings\Contracts\BriefingDataCollector;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

final class BriefingDataCollectorService implements BriefingDataCollector
{
    /**
     * Collect data for a briefing.
     *
     * @param  int  $workspaceId  The workspace ID to collect data for
     * @param  string  $briefingSlug  The briefing template slug
     * @param  array<string, mixed>  $parameters  User-provided parameters
     * @return array<string, mixed> The collected structured data
     */
    public function collect(int $workspaceId, string $briefingSlug, array $parameters): array
    {
        $dateRange = $this->resolveDateRange($parameters);

        return match ($briefingSlug) {
            'standup-update' => $this->collectStandupData($workspaceId, $dateRange, $parameters),
            'weekly-team-summary' => $this->collectTeamSummaryData($workspaceId, $dateRange, $parameters),
            'delivery-velocity' => $this->collectVelocityData($workspaceId, $dateRange, $parameters),
            'engineer-spotlight' => $this->collectEngineerSpotlightData($workspaceId, $dateRange, $parameters),
            'company-update' => $this->collectCompanyUpdateData($workspaceId, $dateRange, $parameters),
            'sprint-retrospective' => $this->collectRetrospectiveData($workspaceId, $dateRange, $parameters),
            'code-health' => $this->collectCodeHealthData($workspaceId, $dateRange, $parameters),
            default => $this->collectGenericData($workspaceId, $dateRange, $parameters),
        };
    }

    /**
     * @param  array<string, mixed>  $structuredData
     * @return array<int, array{type: string, title: string, description: string, value?: mixed}>
     */
    public function detectAchievements(array $structuredData): array
    {
        $achievements = [];

        /** @var array<string, mixed> $summary */
        $summary = $structuredData['summary'] ?? [];

        $this->detectPrMilestone($achievements, (int) ($summary['prs_merged'] ?? 0));
        $this->detectReviewCoverage($achievements, $summary);
        $this->detectActivityStreak($achievements, (int) ($summary['active_days'] ?? 0));
        $this->detectTopContributor($achievements, $structuredData['top_contributor'] ?? null);

        return $achievements;
    }

    /**
     * @param  array<int, array{type: string, title: string, description: string, value?: mixed}>  $achievements
     */
    private function detectPrMilestone(array &$achievements, int $prCount): void
    {
        $milestone = match (true) {
            $prCount >= 100 => ['title' => 'Century Club', 'description' => sprintf('%d pull requests merged!', $prCount)],
            $prCount >= 50 => ['title' => 'Halfway There', 'description' => sprintf('%d pull requests merged this period', $prCount)],
            default => null,
        };

        if ($milestone !== null) {
            $achievements[] = ['type' => 'milestone', ...$milestone, 'value' => $prCount];
        }
    }

    /**
     * @param  array<int, array{type: string, title: string, description: string, value?: mixed}>  $achievements
     * @param  array<string, mixed>  $summary
     */
    private function detectReviewCoverage(array &$achievements, array $summary): void
    {
        $reviewCoverage = isset($summary['review_coverage']) && is_numeric($summary['review_coverage'])
            ? (float) $summary['review_coverage']
            : 0.0;

        if ($reviewCoverage >= 95) {
            $achievements[] = [
                'type' => 'milestone',
                'title' => 'Full Coverage',
                'description' => sprintf('%.1f%% of PRs reviewed by AI', $reviewCoverage),
                'value' => $reviewCoverage,
            ];
        }
    }

    /**
     * @param  array<int, array{type: string, title: string, description: string, value?: mixed}>  $achievements
     */
    private function detectActivityStreak(array &$achievements, int $activeDays): void
    {
        $streak = match (true) {
            $activeDays >= 14 => ['title' => 'Two Week Streak', 'description' => sprintf('%d consecutive days of activity', $activeDays)],
            $activeDays >= 7 => ['title' => 'Week Warrior', 'description' => sprintf('%d consecutive days of activity', $activeDays)],
            default => null,
        };

        if ($streak !== null) {
            $achievements[] = ['type' => 'streak', ...$streak, 'value' => $activeDays];
        }
    }

    /**
     * @param  array<int, array{type: string, title: string, description: string, value?: mixed}>  $achievements
     * @param  array{name?: string, pr_count?: int}|null  $topContributor
     */
    private function detectTopContributor(array &$achievements, ?array $topContributor): void
    {
        if ($topContributor === null) {
            return;
        }

        $prCount = (int) ($topContributor['pr_count'] ?? 0);

        if ($prCount >= 10) {
            $achievements[] = [
                'type' => 'personal_best',
                'title' => 'Star Performer',
                'description' => sprintf('%s merged %d PRs this period', $topContributor['name'] ?? 'A contributor', $prCount),
                'value' => $prCount,
            ];
        }
    }

    /**
     * Resolve the date range from parameters.
     *
     * @param  array<string, mixed>  $parameters  The parameters
     * @return array{start: CarbonInterface, end: CarbonInterface} The date range
     */
    private function resolveDateRange(array $parameters): array
    {
        $end = isset($parameters['end_date'])
            ? Carbon::parse($parameters['end_date'])
            : now();

        $start = isset($parameters['start_date'])
            ? Carbon::parse($parameters['start_date'])
            : $end->copy()->subDays(7);

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Collect data for daily standup briefing.
     *
     * @param  int  $workspaceId  The workspace ID
     * @param  array{start: CarbonInterface, end: CarbonInterface}  $dateRange  The date range
     * @param  array<string, mixed>  $parameters  The parameters
     * @return array<string, mixed> The collected data
     */
    private function collectStandupData(int $workspaceId, array $dateRange, array $parameters): array
    {
        $repositoryIds = $parameters['repository_ids'] ?? [];

        $runsQuery = Run::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);

        if (! empty($repositoryIds)) {
            $runsQuery->whereIn('repository_id', $repositoryIds);
        }

        $runs = $runsQuery->get();

        $completed = $runs->where('status.value', 'completed');
        $inProgress = $runs->where('status.value', 'in_progress');
        $failed = $runs->where('status.value', 'failed');

        return [
            'period' => [
                'start' => $dateRange['start']->toDateString(),
                'end' => $dateRange['end']->toDateString(),
            ],
            'summary' => [
                'total_runs' => $runs->count(),
                'completed' => $completed->count(),
                'in_progress' => $inProgress->count(),
                'failed' => $failed->count(),
                'prs_merged' => $completed->count(),
            ],
            'runs' => $runs->take(20)->map(fn (Run $run): array => [
                'id' => $run->id,
                'pr_number' => $run->pr_number,
                'pr_title' => $run->pr_title,
                'status' => $run->status->value,
                'created_at' => $run->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * Collect data for weekly team summary briefing.
     *
     * @param  int  $workspaceId  The workspace ID
     * @param  array{start: CarbonInterface, end: CarbonInterface}  $dateRange  The date range
     * @param  array<string, mixed>  $parameters  The parameters
     * @return array<string, mixed> The collected data
     */
    private function collectTeamSummaryData(int $workspaceId, array $dateRange, array $parameters): array
    {
        $data = $this->collectStandupData($workspaceId, $dateRange, $parameters);

        // Add team-level metrics
        $repositories = Repository::query()
            ->where('workspace_id', $workspaceId)
            ->get();

        $data['repositories'] = $repositories->map(fn (Repository $repo): array => [
            'id' => $repo->id,
            'name' => $repo->name,
            'full_name' => $repo->full_name,
        ])->values()->all();

        $data['summary']['repository_count'] = $repositories->count();

        return $data;
    }

    /**
     * Collect data for delivery velocity briefing.
     *
     * @param  int  $workspaceId  The workspace ID
     * @param  array{start: CarbonInterface, end: CarbonInterface}  $dateRange  The date range
     * @param  array<string, mixed>  $parameters  The parameters
     * @return array<string, mixed> The collected data
     */
    private function collectVelocityData(int $workspaceId, array $dateRange, array $parameters): array
    {
        $data = $this->collectStandupData($workspaceId, $dateRange, $parameters);

        // Calculate velocity metrics
        $totalDays = (int) $dateRange['start']->diffInDays($dateRange['end']) + 1;
        /** @var array<string, mixed> $summary */
        $summary = $data['summary'] ?? [];
        $prsCompleted = isset($summary['completed']) && is_numeric($summary['completed'])
            ? (int) $summary['completed']
            : 0;

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
     * @param  array{start: CarbonInterface, end: CarbonInterface}  $dateRange  The date range
     * @param  array<string, mixed>  $parameters  The parameters
     * @return array<string, mixed> The collected data
     */
    private function collectEngineerSpotlightData(int $workspaceId, array $dateRange, array $parameters): array
    {
        $data = $this->collectStandupData($workspaceId, $dateRange, $parameters);

        $repositoryIds = $parameters['repository_ids'] ?? [];

        // Get contributor statistics from run metadata
        $runsQuery = Run::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->whereNotNull('metadata->author');

        if (! empty($repositoryIds)) {
            $runsQuery->whereIn('repository_id', $repositoryIds);
        }

        $runs = $runsQuery->get();

        // Group by author
        /** @var array<string, array{name: string, pr_count: int, completed: int}> $contributorStats */
        $contributorStats = [];
        foreach ($runs as $run) {
            $authorValue = $run->metadata['author'] ?? $run->metadata['author_login'] ?? null;
            if ($authorValue === null) {
                continue;
            }

            if (! is_string($authorValue)) {
                continue;
            }

            $author = $authorValue;

            if (! isset($contributorStats[$author])) {
                $contributorStats[$author] = [
                    'name' => $author,
                    'pr_count' => 0,
                    'completed' => 0,
                ];
            }

            $contributorStats[$author]['pr_count']++;
            if ($run->status->value === 'completed') {
                $contributorStats[$author]['completed']++;
            }
        }

        // Sort by PR count and get top contributors
        usort($contributorStats, fn (array $a, array $b): int => $b['pr_count'] <=> $a['pr_count']);

        $data['engineers'] = array_slice($contributorStats, 0, 10);
        $data['top_contributor'] = $contributorStats === [] ? null : $contributorStats[0];

        return $data;
    }

    /**
     * Collect data for company update briefing.
     *
     * @param  int  $workspaceId  The workspace ID
     * @param  array{start: CarbonInterface, end: CarbonInterface}  $dateRange  The date range
     * @param  array<string, mixed>  $parameters  The parameters
     * @return array<string, mixed> The collected data
     */
    private function collectCompanyUpdateData(int $workspaceId, array $dateRange, array $parameters): array
    {
        return $this->collectTeamSummaryData($workspaceId, $dateRange, $parameters);
    }

    /**
     * Collect data for sprint retrospective briefing.
     *
     * @param  int  $workspaceId  The workspace ID
     * @param  array{start: CarbonInterface, end: CarbonInterface}  $dateRange  The date range
     * @param  array<string, mixed>  $parameters  The parameters
     * @return array<string, mixed> The collected data
     */
    private function collectRetrospectiveData(int $workspaceId, array $dateRange, array $parameters): array
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
     * @param  array{start: CarbonInterface, end: CarbonInterface}  $dateRange  The date range
     * @param  array<string, mixed>  $parameters  The parameters
     * @return array<string, mixed> The collected data
     */
    private function collectCodeHealthData(int $workspaceId, array $dateRange, array $parameters): array
    {
        $data = $this->collectStandupData($workspaceId, $dateRange, $parameters);

        $repositoryIds = $parameters['repository_ids'] ?? [];

        // Query findings within the date range
        $findingsQuery = Finding::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);

        if (! empty($repositoryIds)) {
            $findingsQuery->whereHas('run', function (\Illuminate\Database\Eloquent\Builder $query) use ($repositoryIds): void {
                $query->whereIn('repository_id', $repositoryIds);
            });
        }

        $findings = $findingsQuery->get();

        // Count by severity
        $severityCounts = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'info' => 0,
        ];

        foreach ($findings as $finding) {
            $severity = $finding->severity?->value ?? 'info';
            if (array_key_exists($severity, $severityCounts)) {
                $severityCounts[$severity]++;
            }
        }

        // Count by category
        $categoryCounts = [];
        foreach (FindingCategory::cases() as $category) {
            $categoryCounts[$category->value] = 0;
        }

        foreach ($findings as $finding) {
            $category = $finding->category?->value;
            if ($category !== null && isset($categoryCounts[$category])) {
                $categoryCounts[$category]++;
            }
        }

        // Get top issues by severity (critical and high)
        $criticalFindings = $findings->filter(
            fn (Finding $f): bool => $f->severity === SentinelConfigSeverity::Critical
                || $f->severity === SentinelConfigSeverity::High
        )->take(10)->map(fn (Finding $f): array => [
            'id' => $f->id,
            'title' => $f->title,
            'severity' => $f->severity?->value,
            'category' => $f->category?->value,
            'file_path' => $f->file_path,
            'line_start' => $f->line_start,
        ])->values()->all();

        $data['code_health'] = [
            'total_findings' => $findings->count(),
            'critical_issues' => $severityCounts['critical'],
            'high_issues' => $severityCounts['high'],
            'medium_issues' => $severityCounts['medium'],
            'low_issues' => $severityCounts['low'],
            'info_issues' => $severityCounts['info'],
            'severity_breakdown' => $severityCounts,
            'category_breakdown' => $categoryCounts,
            'top_critical_findings' => $criticalFindings,
        ];

        return $data;
    }

    /**
     * Collect generic data for unknown briefing types.
     *
     * @param  int  $workspaceId  The workspace ID
     * @param  array{start: CarbonInterface, end: CarbonInterface}  $dateRange  The date range
     * @param  array<string, mixed>  $parameters  The parameters
     * @return array<string, mixed> The collected data
     */
    private function collectGenericData(int $workspaceId, array $dateRange, array $parameters): array
    {
        Log::warning('Unknown briefing type, using generic data collector', [
            'workspace_id' => $workspaceId,
            'parameters' => $parameters,
        ]);

        return $this->collectStandupData($workspaceId, $dateRange, $parameters);
    }
}
