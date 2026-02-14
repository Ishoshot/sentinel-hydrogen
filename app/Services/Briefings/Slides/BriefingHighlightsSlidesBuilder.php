<?php

declare(strict_types=1);

namespace App\Services\Briefings\Slides;

use App\Services\Briefings\ValueObjects\BriefingAchievements;
use App\Services\Briefings\ValueObjects\BriefingSlide;
use App\Services\Briefings\ValueObjects\BriefingSlideBlock;
use App\Services\Briefings\ValueObjects\BriefingStructuredData;

/**
 * Builds highlights and code-health slides for a briefing deck.
 */
final readonly class BriefingHighlightsSlidesBuilder
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private BriefingHighlightsItemBuilder $highlightsItemBuilder,
        private BriefingCodeHealthBlocksBuilder $codeHealthBlocksBuilder,
    ) {}

    /**
     * Build the highlights-oriented slides.
     *
     * @return array<int, BriefingSlide>
     */
    public function build(BriefingStructuredData $structuredData, BriefingAchievements $achievements): array
    {
        $slides = [];

        $highlightsSlide = $this->buildHighlightsSlide($structuredData->payload(), $achievements);
        if ($highlightsSlide instanceof BriefingSlide) {
            $slides[] = $highlightsSlide;
        }

        $codeHealthSlide = $this->buildCodeHealthSlide($structuredData->payload());
        if ($codeHealthSlide instanceof BriefingSlide) {
            $slides[] = $codeHealthSlide;
        }

        return $slides;
    }

    /**
     * Build a highlights slide from achievements and run data.
     *
     * @param  array<string, mixed>  $payload
     */
    private function buildHighlightsSlide(array $payload, BriefingAchievements $achievements): ?BriefingSlide
    {
        $items = $this->highlightsItemBuilder->build($payload, $achievements);

        if ($items === []) {
            return null;
        }

        return new BriefingSlide(
            id: 'highlights',
            type: 'highlights',
            title: 'Highlights',
            subtitle: null,
            blocks: [BriefingSlideBlock::list($items)],
        );
    }

    /**
     * Build a code health slide when code health data exists.
     *
     * @param  array<string, mixed>  $payload
     */
    private function buildCodeHealthSlide(array $payload): ?BriefingSlide
    {
        $codeHealth = $payload['code_health'] ?? null;
        if (! is_array($codeHealth)) {
            return null;
        }

        /** @var array<string, mixed> $codeHealthData */
        $codeHealthData = $codeHealth;

        return new BriefingSlide(
            id: 'code-health',
            type: 'code_health',
            title: 'Code Health',
            subtitle: null,
            blocks: $this->codeHealthBlocksBuilder->build($codeHealthData),
        );
    }
}
