<?php

declare(strict_types=1);

namespace App\Services\Briefings;

use App\Services\Briefings\ValueObjects\BriefingDataQuality;
use App\Services\Briefings\ValueObjects\BriefingEvidence;

/**
 * Shared builders for briefing payload concerns.
 */
final class BriefingPayloadSupport
{
    private const int DATA_SPARSE_RUN_THRESHOLD = 5;

    /**
     * Build a data quality summary for the briefing payload.
     */
    public function buildDataQuality(
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

        return new BriefingDataQuality(
            isSparse: $totalRuns < self::DATA_SPARSE_RUN_THRESHOLD,
            totalRuns: $totalRuns,
            activeDays: $activeDays,
            periodDays: $periodDays,
            reviewCoverage: $reviewCoverage,
            notes: $notes,
        );
    }

    /**
     * Build an evidence trail from source identifiers.
     *
     * @param  array<int, int>  $runIds
     * @param  array<int, int>  $findingIds
     * @param  array<int, string>  $repositoryNames
     */
    public function buildEvidence(
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
