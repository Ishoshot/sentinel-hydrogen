<?php

declare(strict_types=1);

namespace App\Services\Briefings\Templates;

use App\Services\Briefings\Contracts\BriefingTemplateDataCollector;
use App\Services\Briefings\ValueObjects\BriefingDateRange;

/**
 * Collects payload data for the sprint retrospective template.
 */
final readonly class SprintRetrospectiveTemplateCollector implements BriefingTemplateDataCollector
{
    /**
     * Create a new sprint retrospective collector instance.
     */
    public function __construct(private WeeklyTeamSummaryTemplateCollector $weeklyTeamSummaryCollector) {}

    /**
     * {@inheritdoc}
     */
    public function slug(): string
    {
        return 'sprint-retrospective';
    }

    /**
     * {@inheritdoc}
     */
    public function collect(int $workspaceId, BriefingDateRange $dateRange, array $parameters): array
    {
        $data = $this->weeklyTeamSummaryCollector->collect($workspaceId, $dateRange, $parameters);

        $data['retrospective'] = [
            'sprint_goal' => $parameters['sprint_goal'] ?? null,
            'sprint_number' => $parameters['sprint_number'] ?? null,
        ];

        return $data;
    }
}
