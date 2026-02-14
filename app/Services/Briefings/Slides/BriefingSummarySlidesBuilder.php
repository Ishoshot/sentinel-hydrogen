<?php

declare(strict_types=1);

namespace App\Services\Briefings\Slides;

use App\Models\Briefing;
use App\Services\Briefings\Slides\Factories\SlideMetricsFactory;
use App\Services\Briefings\Slides\Resolvers\SlideSummaryTextResolver;
use App\Services\Briefings\ValueObjects\BriefingSlide;
use App\Services\Briefings\ValueObjects\BriefingSlideBlock;
use App\Services\Briefings\ValueObjects\BriefingStructuredData;
use App\Services\Briefings\ValueObjects\BriefingSummary;

/**
 * Builds summary-oriented slides for a briefing deck.
 */
final readonly class BriefingSummarySlidesBuilder
{
    /**
     * Create a new slides builder instance.
     */
    public function __construct(
        private SlideSummaryTextResolver $textResolver = new SlideSummaryTextResolver,
        private SlideMetricsFactory $metricsFactory = new SlideMetricsFactory,
    ) {}

    /**
     * Build the core title, summary, and metrics slides.
     *
     * @return array<int, BriefingSlide>
     */
    public function build(Briefing $briefing, BriefingStructuredData $structuredData, ?string $narrative): array
    {
        $period = $structuredData->period();
        $summary = $structuredData->summary();

        return [
            $this->buildTitleSlide($briefing->title, $briefing->description, $period->start, $period->end),
            $this->buildSummarySlide($summary, $period->start, $period->end, $narrative),
            $this->buildMetricsSlide($summary),
        ];
    }

    /**
     * Build the title slide.
     */
    private function buildTitleSlide(
        string $title,
        ?string $description,
        string $periodStart,
        string $periodEnd,
    ): BriefingSlide {
        $blocks = [];

        if ($description !== null && $description !== '') {
            $blocks[] = BriefingSlideBlock::text($description);
        }

        return new BriefingSlide(
            id: 'title',
            type: 'title',
            title: $title,
            subtitle: $this->textResolver->formatPeriodRange($periodStart, $periodEnd),
            blocks: $blocks,
        );
    }

    /**
     * Build a narrative summary slide.
     */
    private function buildSummarySlide(
        BriefingSummary $summary,
        string $periodStart,
        string $periodEnd,
        ?string $narrative,
    ): BriefingSlide {
        $text = $this->textResolver->resolve($summary, $periodStart, $periodEnd, $narrative);

        return new BriefingSlide(
            id: 'summary',
            type: 'summary',
            title: 'Summary',
            subtitle: null,
            blocks: [BriefingSlideBlock::text($text)],
        );
    }

    /**
     * Build a metrics slide from summary data.
     */
    private function buildMetricsSlide(BriefingSummary $summary): BriefingSlide
    {
        return new BriefingSlide(
            id: 'metrics',
            type: 'metrics',
            title: 'Key Metrics',
            subtitle: null,
            blocks: [BriefingSlideBlock::metrics($this->metricsFactory->build($summary))],
        );
    }
}
