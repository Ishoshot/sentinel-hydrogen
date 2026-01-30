<?php

declare(strict_types=1);

namespace App\Services\Briefings\ValueObjects;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final readonly class BriefingDateRange
{
    /**
     * @param  CarbonInterface  $start  Start date for the briefing window
     * @param  CarbonInterface  $end  End date for the briefing window
     */
    public function __construct(
        public CarbonInterface $start,
        public CarbonInterface $end,
    ) {}

    /**
     * Create a date range from briefing parameters.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function fromArray(array $parameters): self
    {
        $end = isset($parameters['end_date'])
            ? Carbon::parse($parameters['end_date'])
            : now();

        $start = isset($parameters['start_date'])
            ? Carbon::parse($parameters['start_date'])
            : $end->copy()->subDays(7);

        return new self($start, $end);
    }

    /**
     * Convert the date range into a reporting period value object.
     */
    public function toPeriod(): BriefingPeriod
    {
        return BriefingPeriod::fromDates($this->start, $this->end);
    }

    /**
     * Get the number of days in the date range (inclusive).
     */
    public function days(): int
    {
        return (int) $this->start->diffInDays($this->end) + 1;
    }
}
