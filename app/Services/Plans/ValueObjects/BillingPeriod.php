<?php

declare(strict_types=1);

namespace App\Services\Plans\ValueObjects;

use App\Models\Workspace;
use Carbon\CarbonImmutable;

/**
 * Represents a billing period with start and end dates.
 */
final readonly class BillingPeriod
{
    /**
     * Create a new BillingPeriod instance.
     */
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}

    /**
     * Resolve the billing period for a workspace from its latest subscription.
     *
     * Uses the subscription's current_period_start/end if available,
     * otherwise falls back to the current calendar month.
     */
    public static function forWorkspace(Workspace $workspace): self
    {
        $subscription = $workspace->subscriptions()->latest()->first();

        if ($subscription?->current_period_start !== null && $subscription?->current_period_end !== null) {
            return new self(
                start: CarbonImmutable::parse($subscription->current_period_start),
                end: CarbonImmutable::parse($subscription->current_period_end),
            );
        }

        return self::currentMonth();
    }

    /**
     * Create a billing period for the current month.
     */
    public static function currentMonth(): self
    {
        $start = CarbonImmutable::now()->startOfMonth();

        return new self(
            start: $start,
            end: $start->endOfMonth(),
        );
    }

    /**
     * Create a billing period for a specific month.
     */
    public static function forMonth(int $year, int $month): self
    {
        $start = CarbonImmutable::createFromDate($year, $month, 1)->startOfMonth();

        return new self(
            start: $start,
            end: $start->endOfMonth(),
        );
    }

    /**
     * Check if a date falls within this billing period.
     */
    public function contains(CarbonImmutable $date): bool
    {
        return $date->between($this->start, $this->end);
    }

    /**
     * Get the number of days in this period.
     */
    public function daysInPeriod(): int
    {
        return (int) $this->start->diffInDays($this->end) + 1;
    }

    /**
     * Get the number of days remaining in this period.
     */
    public function daysRemaining(): int
    {
        $now = CarbonImmutable::now();

        if ($now->isAfter($this->end)) {
            return 0;
        }

        if ($now->isBefore($this->start)) {
            return $this->daysInPeriod();
        }

        return (int) $now->diffInDays($this->end);
    }

    /**
     * Get start date as datetime string.
     */
    public function startAsString(): string
    {
        return $this->start->toDateTimeString();
    }

    /**
     * Get end date as datetime string.
     */
    public function endAsString(): string
    {
        return $this->end->toDateTimeString();
    }

    /**
     * Convert to array for serialization.
     *
     * @return array{start: string, end: string}
     */
    public function toArray(): array
    {
        return [
            'start' => $this->start->toIso8601String(),
            'end' => $this->end->toIso8601String(),
        ];
    }
}
