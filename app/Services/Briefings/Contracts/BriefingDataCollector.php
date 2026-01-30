<?php

declare(strict_types=1);

namespace App\Services\Briefings\Contracts;

use App\Services\Briefings\ValueObjects\BriefingAchievements;
use App\Services\Briefings\ValueObjects\BriefingParameters;
use App\Services\Briefings\ValueObjects\BriefingStructuredData;

interface BriefingDataCollector
{
    /**
     * Collect data for a briefing.
     *
     * @param  int  $workspaceId  The workspace ID to collect data for
     * @param  string  $briefingSlug  The briefing template slug
     * @param  BriefingParameters  $parameters  User-provided parameters
     * @return BriefingStructuredData The collected structured data
     */
    public function collect(int $workspaceId, string $briefingSlug, BriefingParameters $parameters): BriefingStructuredData;

    /**
     * Detect achievements from the collected data.
     *
     * @param  BriefingStructuredData  $structuredData  The collected data
     * @return BriefingAchievements Detected achievements
     */
    public function detectAchievements(BriefingStructuredData $structuredData): BriefingAchievements;
}
