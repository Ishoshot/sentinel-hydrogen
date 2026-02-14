<?php

declare(strict_types=1);

namespace App\Services\Briefings\Templates;

use App\Services\Briefings\Contracts\BriefingTemplateDataCollector;
use App\Services\Briefings\ValueObjects\BriefingDateRange;

/**
 * Collects payload data for the company update template.
 */
final readonly class CompanyUpdateTemplateCollector implements BriefingTemplateDataCollector
{
    /**
     * Create a new company update collector instance.
     */
    public function __construct(private WeeklyTeamSummaryTemplateCollector $weeklyTeamSummaryCollector) {}

    /**
     * {@inheritdoc}
     */
    public function slug(): string
    {
        return 'company-update';
    }

    /**
     * {@inheritdoc}
     */
    public function collect(int $workspaceId, BriefingDateRange $dateRange, array $parameters): array
    {
        return $this->weeklyTeamSummaryCollector->collect($workspaceId, $dateRange, $parameters);
    }
}
