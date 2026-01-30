<?php

declare(strict_types=1);

namespace App\Services\Briefings\ValueObjects;

final readonly class BriefingSummary
{
    /**
     * @param  array<string, mixed>  $metrics
     */
    public function __construct(
        public array $metrics = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->metrics;
    }

    /**
     * Get a metric value by key.
     */
    public function metric(string $key, mixed $default = null): mixed
    {
        return $this->metrics[$key] ?? $default;
    }

    /**
     * Get the total number of runs.
     */
    public function totalRuns(): int
    {
        return (int) ($this->metrics['total_runs'] ?? 0);
    }

    /**
     * Get the number of completed runs.
     */
    public function completed(): int
    {
        return (int) ($this->metrics['completed'] ?? 0);
    }

    /**
     * Get the number of in-progress runs.
     */
    public function inProgress(): int
    {
        return (int) ($this->metrics['in_progress'] ?? 0);
    }

    /**
     * Get the number of failed runs.
     */
    public function failed(): int
    {
        return (int) ($this->metrics['failed'] ?? 0);
    }

    /**
     * Get the count of merged pull requests.
     */
    public function prsMerged(): int
    {
        return (int) ($this->metrics['prs_merged'] ?? 0);
    }

    /**
     * Get the count of active days.
     */
    public function activeDays(): int
    {
        return (int) ($this->metrics['active_days'] ?? 0);
    }

    /**
     * Get the review coverage percentage.
     */
    public function reviewCoverage(): float
    {
        return is_numeric($this->metrics['review_coverage'] ?? null)
            ? (float) $this->metrics['review_coverage']
            : 0.0;
    }

    /**
     * Get the count of repositories in the period.
     */
    public function repositoryCount(): int
    {
        return (int) ($this->metrics['repository_count'] ?? 0);
    }
}
