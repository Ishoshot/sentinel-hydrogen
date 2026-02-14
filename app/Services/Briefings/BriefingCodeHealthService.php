<?php

declare(strict_types=1);

namespace App\Services\Briefings;

use App\Models\Finding;
use App\Models\Run;
use App\Services\Briefings\Support\CodeHealthAggregator;
use App\Services\Briefings\Support\CodeHealthCriticalFindingsFetcher;
use App\Services\Briefings\ValueObjects\BriefingDateRange;

/**
 * Aggregates code-health metrics for briefing payloads.
 */
final readonly class BriefingCodeHealthService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private CodeHealthAggregator $aggregator = new CodeHealthAggregator,
        private CodeHealthCriticalFindingsFetcher $criticalFindingsFetcher = new CodeHealthCriticalFindingsFetcher,
    ) {}

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

        $aggregation = $this->aggregator->aggregate($findingsQuery);
        $criticalFindings = $this->criticalFindingsFetcher->fetch($findingsQuery);

        return [
            'code_health' => [
                'total_findings' => $aggregation['total_findings'],
                'critical_issues' => $aggregation['severity_counts']['critical'],
                'high_issues' => $aggregation['severity_counts']['high'],
                'medium_issues' => $aggregation['severity_counts']['medium'],
                'low_issues' => $aggregation['severity_counts']['low'],
                'info_issues' => $aggregation['severity_counts']['info'],
                'severity_breakdown' => $aggregation['severity_counts'],
                'category_breakdown' => $aggregation['category_counts'],
                'top_critical_findings' => $criticalFindings,
            ],
            'critical_finding_ids' => array_map(
                static fn (mixed $id): int => $id,
                array_column($criticalFindings, 'id')
            ),
        ];
    }
}
