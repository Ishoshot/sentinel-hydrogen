<?php

declare(strict_types=1);

namespace App\Services\Briefings\Templates;

use App\Models\Repository;
use App\Services\Briefings\BriefingPayloadSupport;
use App\Services\Briefings\Contracts\BriefingTemplateDataCollector;
use App\Services\Briefings\ValueObjects\BriefingDateRange;
use App\Services\Briefings\ValueObjects\BriefingEvidence;
use App\Services\Briefings\ValueObjects\BriefingSummary;

/**
 * Collects payload data for the weekly team summary template.
 */
final readonly class WeeklyTeamSummaryTemplateCollector implements BriefingTemplateDataCollector
{
    private const int REPOSITORY_LIST_LIMIT = 25;

    /**
     * Create a new weekly team summary collector instance.
     */
    public function __construct(
        private StandupUpdateTemplateCollector $standupCollector,
        private BriefingPayloadSupport $payloadSupport,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function slug(): string
    {
        return 'weekly-team-summary';
    }

    /**
     * {@inheritdoc}
     */
    public function collect(int $workspaceId, BriefingDateRange $dateRange, array $parameters): array
    {
        $data = $this->standupCollector->collect($workspaceId, $dateRange, $parameters);
        $repositoryIds = $parameters['repository_ids'] ?? [];

        $repositoriesQuery = Repository::query()
            ->where('workspace_id', $workspaceId);

        if ($repositoryIds !== []) {
            $repositoriesQuery->whereIn('id', $repositoryIds);
        }

        $repositoryCount = (int) (clone $repositoriesQuery)->count();

        $repositories = (clone $repositoriesQuery)
            ->orderBy('id')
            ->limit(self::REPOSITORY_LIST_LIMIT)
            ->get(['id', 'name', 'full_name']);

        $data['repositories'] = $repositories->map(fn (Repository $repository): array => [
            'id' => $repository->id,
            'name' => $repository->name,
            'full_name' => $repository->full_name,
        ])->values()->all();

        /** @var array<string, mixed> $summaryPayload */
        $summaryPayload = is_array($data['summary'] ?? null) ? $data['summary'] : [];
        $summaryPayload['repository_count'] = $repositoryCount;
        $summary = BriefingSummary::fromArray($summaryPayload);

        $dataQuality = $this->payloadSupport->buildDataQuality(
            totalRuns: $summary->totalRuns(),
            activeDays: $summary->activeDays(),
            periodDays: $dateRange->days(),
            reviewCoverage: $summary->reviewCoverage(),
            repositoryCount: $repositoryCount,
        );

        /** @var array<string, mixed> $evidencePayload */
        $evidencePayload = is_array($data['evidence'] ?? null) ? $data['evidence'] : [];
        $existingEvidence = BriefingEvidence::fromArray($evidencePayload);

        $evidence = $this->payloadSupport->buildEvidence(
            runIds: $existingEvidence->runIds,
            repositoryNames: $repositories->map(
                fn (Repository $repository): string => (string) ($repository->full_name ?? $repository->name ?? '')
            )->filter()->values()->all(),
        );

        $data['summary'] = $summary->toArray();
        $data['data_quality'] = $dataQuality->toArray();
        $data['evidence'] = $evidence->toArray();

        return $data;
    }
}
