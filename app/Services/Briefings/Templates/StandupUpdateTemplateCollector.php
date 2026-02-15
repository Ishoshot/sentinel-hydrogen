<?php

declare(strict_types=1);

namespace App\Services\Briefings\Templates;

use App\Models\Run;
use App\Services\Briefings\BriefingRunMetricsService;
use App\Services\Briefings\Contracts\BriefingTemplateDataCollector;
use App\Services\Briefings\Factories\BriefingPayloadFactory;
use App\Services\Briefings\ValueObjects\BriefingDateRange;
use App\Services\Briefings\ValueObjects\BriefingSummary;
use Illuminate\Support\Collection;

/**
 * Collects payload data for the standup update briefing template.
 */
final readonly class StandupUpdateTemplateCollector implements BriefingTemplateDataCollector
{
    private const int RUN_ACTIVITY_LIMIT = 20;

    /**
     * Create a new standup collector instance.
     */
    public function __construct(
        private BriefingRunMetricsService $runMetricsService,
        private BriefingPayloadFactory $payloadFactory,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function slug(): string
    {
        return 'standup-update';
    }

    /**
     * {@inheritdoc}
     */
    public function collect(int $workspaceId, BriefingDateRange $dateRange, array $parameters): array
    {
        $repositoryIds = $parameters['repository_ids'] ?? [];

        $runsQuery = $this->runMetricsService->buildRunsQuery($workspaceId, $dateRange, $repositoryIds);
        $summaryRow = $this->runMetricsService->fetchRunSummary($runsQuery);

        $totalRuns = (int) ($summaryRow->total_runs ?? 0);
        $completedRuns = (int) ($summaryRow->completed ?? 0);
        $inProgressRuns = (int) ($summaryRow->in_progress ?? 0);
        $failedRuns = (int) ($summaryRow->failed ?? 0);
        $activeDays = (int) ($summaryRow->active_days ?? 0);

        $reviewCoverage = $this->runMetricsService->calculateReviewCoverage($workspaceId, $dateRange, $repositoryIds);

        /** @var Collection<int, Run> $recentRuns */
        $recentRuns = (clone $runsQuery)
            ->orderByDesc('created_at')
            ->limit(self::RUN_ACTIVITY_LIMIT)
            ->get();

        $dataQuality = $this->payloadFactory->buildDataQuality(
            totalRuns: $totalRuns,
            activeDays: $activeDays,
            periodDays: $dateRange->days(),
            reviewCoverage: $reviewCoverage,
        );

        $runIds = $recentRuns
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        $evidence = $this->payloadFactory->buildEvidence(runIds: $runIds);

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
}
