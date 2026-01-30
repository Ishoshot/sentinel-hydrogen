<?php

declare(strict_types=1);

namespace App\Services\Briefings\ValueObjects;

/**
 * A structured slide deck payload for a briefing.
 */
final readonly class BriefingSlideDeck
{
    /**
     * @param  string  $version  Schema version identifier
     * @param  string  $title  The deck title
     * @param  BriefingPeriod  $period  The reporting period
     * @param  string  $generatedAt  ISO8601 generation timestamp
     * @param  array<int, BriefingSlide>  $slides  Slides in the deck
     * @param  array<string, mixed>  $meta  Additional metadata
     */
    public function __construct(
        public string $version,
        public string $title,
        public BriefingPeriod $period,
        public string $generatedAt,
        public array $slides = [],
        public array $meta = [],
    ) {}

    /**
     * @return array{version: string, title: string, period: array{start: string, end: string}, generated_at: string, slides: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'title' => $this->title,
            'period' => $this->period->toArray(),
            'generated_at' => $this->generatedAt,
            'slides' => array_map(
                static fn (BriefingSlide $slide): array => $slide->toArray(),
                $this->slides,
            ),
            'meta' => $this->meta,
        ];
    }
}
