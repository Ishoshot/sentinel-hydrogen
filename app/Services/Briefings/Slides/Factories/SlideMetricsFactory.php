<?php

declare(strict_types=1);

namespace App\Services\Briefings\Slides\Factories;

use App\Services\Briefings\ValueObjects\BriefingSlideMetric;
use App\Services\Briefings\ValueObjects\BriefingSummary;

/**
 * Builds slide metric entries from summary data.
 */
final readonly class SlideMetricsFactory
{
    /**
     * Build the metrics list from summary data.
     *
     * @return array<int, BriefingSlideMetric>
     */
    public function build(BriefingSummary $summary): array
    {
        return [
            new BriefingSlideMetric('Total Runs', $summary->totalRuns()),
            new BriefingSlideMetric('Completed', $summary->completed()),
            new BriefingSlideMetric('In Progress', $summary->inProgress()),
            new BriefingSlideMetric('Failed', $summary->failed()),
            new BriefingSlideMetric('Active Days', $summary->activeDays()),
            new BriefingSlideMetric('Review Coverage', $summary->reviewCoverage(), '%'),
            new BriefingSlideMetric('Active Repositories', $summary->repositoryCount()),
        ];
    }
}
