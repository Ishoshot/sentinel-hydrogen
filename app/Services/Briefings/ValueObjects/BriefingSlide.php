<?php

declare(strict_types=1);

namespace App\Services\Briefings\ValueObjects;

/**
 * A single slide in a briefing deck.
 */
final readonly class BriefingSlide
{
    /**
     * @param  string  $id  Stable identifier for the slide
     * @param  string  $type  The slide type
     * @param  string  $title  The slide title
     * @param  string|null  $subtitle  Optional slide subtitle
     * @param  array<int, BriefingSlideBlock>  $blocks  Slide content blocks
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $title,
        public ?string $subtitle = null,
        public array $blocks = [],
    ) {}

    /**
     * @return array{type: string, id: string, title: string, subtitle?: string|null, blocks: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'blocks' => array_map(
                static fn (BriefingSlideBlock $block): array => $block->toArray(),
                $this->blocks,
            ),
        ];
    }
}
