<?php

declare(strict_types=1);

namespace App\Services\Briefings\ValueObjects;

/**
 * A single metric displayed on a slide.
 */
final readonly class BriefingSlideMetric
{
    /**
     * @param  string  $label  The metric label
     * @param  float|int|string  $value  The metric value
     * @param  string|null  $unit  Optional metric unit
     */
    public function __construct(
        public string $label,
        public float|int|string $value,
        public ?string $unit = null,
    ) {}

    /**
     * @return array{label: string, value: float|int|string, unit?: string}
     */
    public function toArray(): array
    {
        $payload = [
            'label' => $this->label,
            'value' => $this->value,
        ];

        if ($this->unit !== null) {
            $payload['unit'] = $this->unit;
        }

        return $payload;
    }
}
