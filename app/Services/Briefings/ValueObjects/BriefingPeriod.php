<?php

declare(strict_types=1);

namespace App\Services\Briefings\ValueObjects;

use Carbon\CarbonInterface;

/**
 * Represents the date boundaries for a briefing period.
 */
final readonly class BriefingPeriod
{
    /**
     * @param  string  $start  The start date (Y-m-d)
     * @param  string  $end  The end date (Y-m-d)
     */
    public function __construct(
        public string $start,
        public string $end,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $start = isset($payload['start']) ? (string) $payload['start'] : '';
        $end = isset($payload['end']) ? (string) $payload['end'] : '';

        return new self($start, $end);
    }

    /**
     * Create a briefing period from Carbon date instances.
     */
    public static function fromDates(CarbonInterface $start, CarbonInterface $end): self
    {
        return new self($start->toDateString(), $end->toDateString());
    }

    /**
     * @return array{start: string, end: string}
     */
    public function toArray(): array
    {
        return [
            'start' => $this->start,
            'end' => $this->end,
        ];
    }
}
