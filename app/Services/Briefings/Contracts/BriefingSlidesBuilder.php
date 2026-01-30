<?php

declare(strict_types=1);

namespace App\Services\Briefings\Contracts;

use App\Models\Briefing;
use App\Services\Briefings\ValueObjects\BriefingAchievements;
use App\Services\Briefings\ValueObjects\BriefingSlideDeck;
use App\Services\Briefings\ValueObjects\BriefingStructuredData;

interface BriefingSlidesBuilder
{
    /**
     * Build a structured slide deck for a briefing.
     */
    public function build(
        Briefing $briefing,
        BriefingStructuredData $structuredData,
        BriefingAchievements $achievements,
        ?string $narrative,
    ): BriefingSlideDeck;
}
