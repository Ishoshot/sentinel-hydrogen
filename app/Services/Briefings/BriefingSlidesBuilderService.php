<?php

declare(strict_types=1);

namespace App\Services\Briefings;

use App\Models\Briefing;
use App\Services\Briefings\Contracts\BriefingSlidesBuilder;
use App\Services\Briefings\Slides\BriefingDiagnosticsSlidesBuilder;
use App\Services\Briefings\Slides\BriefingHighlightsSlidesBuilder;
use App\Services\Briefings\Slides\BriefingSummarySlidesBuilder;
use App\Services\Briefings\ValueObjects\BriefingAchievements;
use App\Services\Briefings\ValueObjects\BriefingSlideDeck;
use App\Services\Briefings\ValueObjects\BriefingStructuredData;
use Carbon\CarbonImmutable;

/**
 * Build structured slide decks from briefing data.
 */
final readonly class BriefingSlidesBuilderService implements BriefingSlidesBuilder
{
    private const string SCHEMA_VERSION = '1.0';

    /**
     * Create a new slide deck builder instance.
     */
    public function __construct(
        private BriefingSummarySlidesBuilder $summarySlidesBuilder,
        private BriefingHighlightsSlidesBuilder $highlightsSlidesBuilder,
        private BriefingDiagnosticsSlidesBuilder $diagnosticsSlidesBuilder,
    ) {}

    /**
     * Build a structured slide deck for a briefing.
     */
    public function build(
        Briefing $briefing,
        BriefingStructuredData $structuredData,
        BriefingAchievements $achievements,
        ?string $narrative,
    ): BriefingSlideDeck {
        $period = $structuredData->period();

        $slides = [
            ...$this->summarySlidesBuilder->build($briefing, $structuredData, $narrative),
            ...$this->highlightsSlidesBuilder->build($structuredData, $achievements),
            ...$this->diagnosticsSlidesBuilder->build($structuredData),
        ];

        return new BriefingSlideDeck(
            version: self::SCHEMA_VERSION,
            title: $briefing->title,
            period: $period,
            generatedAt: CarbonImmutable::now()->toIso8601String(),
            slides: $slides,
            meta: [
                'briefing_slug' => $briefing->slug,
            ],
        );
    }
}
