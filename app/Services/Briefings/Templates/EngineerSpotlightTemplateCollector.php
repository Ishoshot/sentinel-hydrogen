<?php

declare(strict_types=1);

namespace App\Services\Briefings\Templates;

use App\Enums\Reviews\RunStatus;
use App\Models\Run;
use App\Services\Briefings\Contracts\BriefingTemplateDataCollector;
use App\Services\Briefings\ValueObjects\BriefingDateRange;
use App\Services\Briefings\ValueObjects\BriefingTopContributor;

/**
 * Collects payload data for the engineer spotlight template.
 */
final readonly class EngineerSpotlightTemplateCollector implements BriefingTemplateDataCollector
{
    private const int ENGINEER_LIMIT = 10;

    /**
     * Create a new engineer spotlight collector instance.
     */
    public function __construct(private StandupUpdateTemplateCollector $standupCollector) {}

    /**
     * {@inheritdoc}
     */
    public function slug(): string
    {
        return 'engineer-spotlight';
    }

    /**
     * {@inheritdoc}
     */
    public function collect(int $workspaceId, BriefingDateRange $dateRange, array $parameters): array
    {
        $data = $this->standupCollector->collect($workspaceId, $dateRange, $parameters);
        $repositoryIds = $parameters['repository_ids'] ?? [];
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

        if ($repositoryIds !== []) {
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
}
