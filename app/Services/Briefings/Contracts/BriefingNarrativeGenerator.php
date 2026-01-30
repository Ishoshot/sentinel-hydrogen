<?php

declare(strict_types=1);

namespace App\Services\Briefings\Contracts;

use App\Services\Briefings\ValueObjects\BriefingAchievements;
use App\Services\Briefings\ValueObjects\BriefingExcerpts;
use App\Services\Briefings\ValueObjects\BriefingStructuredData;
use App\Services\Briefings\ValueObjects\NarrativeGenerationResult;

interface BriefingNarrativeGenerator
{
    /**
     * Generate a narrative from structured data.
     *
     * @param  string  $promptPath  The Blade template path for the prompt
     * @param  BriefingStructuredData  $structuredData  The collected data
     * @param  BriefingAchievements  $achievements  Detected achievements
     * @return NarrativeGenerationResult The generated narrative with telemetry
     */
    public function generate(string $promptPath, BriefingStructuredData $structuredData, BriefingAchievements $achievements): NarrativeGenerationResult;

    /**
     * Generate smart excerpts for various channels.
     *
     * @param  string  $narrative  The full narrative
     * @param  BriefingStructuredData  $structuredData  The collected data
     * @return BriefingExcerpts Excerpts keyed by channel (slack, email, linkedin, short)
     */
    public function generateExcerpts(string $narrative, BriefingStructuredData $structuredData): BriefingExcerpts;
}
