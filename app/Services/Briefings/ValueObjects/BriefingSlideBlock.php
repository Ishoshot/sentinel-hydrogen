<?php

declare(strict_types=1);

namespace App\Services\Briefings\ValueObjects;

/**
 * A content block within a slide.
 */
final readonly class BriefingSlideBlock
{
    /**
     * @param  string  $type  The block type
     * @param  array<string, mixed>  $data  The block payload
     */
    public function __construct(
        public string $type,
        public array $data = [],
    ) {}

    /**
     * Create a text block.
     */
    public static function text(string $text): self
    {
        return new self('text', [
            'text' => $text,
        ]);
    }

    /**
     * Create a metrics block.
     *
     * @param  array<int, BriefingSlideMetric>  $metrics
     */
    public static function metrics(array $metrics): self
    {
        return new self('metrics', [
            'items' => array_map(
                static fn (BriefingSlideMetric $metric): array => $metric->toArray(),
                $metrics,
            ),
        ]);
    }

    /**
     * Create a list block.
     *
     * @param  array<int, string>  $items
     */
    public static function list(array $items, ?string $title = null): self
    {
        $payload = [
            'items' => $items,
        ];

        if ($title !== null && $title !== '') {
            $payload['title'] = $title;
        }

        return new self('list', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            ...$this->data,
        ];
    }
}
