<?php

declare(strict_types=1);

namespace App\Services\Briefings;

use App\Enums\Reviews\FindingCategory;
use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Models\Finding;
use App\Models\Run;
use App\Services\Briefings\ValueObjects\BriefingDateRange;

/**
 * Aggregates code-health metrics for briefing payloads.
 */
final class BriefingCodeHealthService
{
    private const int CRITICAL_FINDINGS_LIMIT = 10;

    /**
     * @param  array<int, int>  $repositoryIds
     * @return array{
     *     code_health: array{
     *         total_findings: int,
     *         critical_issues: int,
     *         high_issues: int,
     *         medium_issues: int,
     *         low_issues: int,
     *         info_issues: int,
     *         severity_breakdown: array<string, int>,
     *         category_breakdown: array<string, int>,
     *         top_critical_findings: array<int, array{id: int, title: string, severity: string|null, category: string|null, file_path: string|null, line_start: int|null}>
     *     },
     *     critical_finding_ids: array<int, int>
     * }
     */
    public function collect(int $workspaceId, BriefingDateRange $dateRange, array $repositoryIds = []): array
    {
        $findingsQuery = Finding::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('created_at', [$dateRange->start, $dateRange->end]);

        if ($repositoryIds !== []) {
            $runIdsSubquery = Run::query()
                ->select('id')
                ->where('workspace_id', $workspaceId)
                ->whereIn('repository_id', $repositoryIds);

            $findingsQuery->whereIn('run_id', $runIdsSubquery);
        }

        $aggregateRow = (clone $findingsQuery)
            ->selectRaw('COUNT(*) as total_findings')
            ->selectRaw('severity')
            ->selectRaw('category')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('severity', 'category')
            ->get();

        $totalFindings = 0;
        $severityCounts = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'info' => 0,
        ];

        $categoryCounts = [];
        foreach (FindingCategory::cases() as $category) {
            $categoryCounts[$category->value] = 0;
        }

        foreach ($aggregateRow as $row) {
            $count = (int) $row->getAttribute('count');
            $totalFindings += $count;

            $severity = $row->severity instanceof SentinelConfigSeverity
                ? $row->severity->value
                : (string) $row->severity;
            if (array_key_exists($severity, $severityCounts)) {
                $severityCounts[$severity] += $count;
            }

            $category = $row->category instanceof FindingCategory
                ? $row->category->value
                : (string) $row->category;
            if ($category !== '' && isset($categoryCounts[$category])) {
                $categoryCounts[$category] += $count;
            }
        }

        $criticalFindings = (clone $findingsQuery)
            ->whereIn('severity', [
                SentinelConfigSeverity::Critical->value,
                SentinelConfigSeverity::High->value,
            ])
            ->orderByRaw('CASE WHEN severity = ? THEN 0 ELSE 1 END', [SentinelConfigSeverity::Critical->value])
            ->orderByDesc('created_at')
            ->limit(self::CRITICAL_FINDINGS_LIMIT)
            ->get(['id', 'title', 'severity', 'category', 'file_path', 'line_start'])
            ->map(fn (Finding $finding): array => [
                'id' => $finding->id,
                'title' => $finding->title,
                'severity' => $finding->severity?->value,
                'category' => $finding->category?->value,
                'file_path' => $finding->file_path,
                'line_start' => $finding->line_start,
            ])
            ->values()
            ->all();

        return [
            'code_health' => [
                'total_findings' => $totalFindings,
                'critical_issues' => $severityCounts['critical'],
                'high_issues' => $severityCounts['high'],
                'medium_issues' => $severityCounts['medium'],
                'low_issues' => $severityCounts['low'],
                'info_issues' => $severityCounts['info'],
                'severity_breakdown' => $severityCounts,
                'category_breakdown' => $categoryCounts,
                'top_critical_findings' => $criticalFindings,
            ],
            'critical_finding_ids' => array_map(
                static fn (mixed $id): int => (int) $id,
                array_column($criticalFindings, 'id')
            ),
        ];
    }
}
