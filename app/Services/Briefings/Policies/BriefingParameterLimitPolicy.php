<?php

declare(strict_types=1);

namespace App\Services\Briefings\Policies;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class BriefingParameterLimitPolicy
{
    /**
     * Enforce global briefing parameter limits from configuration.
     *
     * @param  array<string, mixed>  $parameters
     *
     * @throws ValidationException
     */
    public function enforce(array $parameters): void
    {
        $this->enforceRepositoryLimit($parameters);
        $this->enforceDateRangeLimit($parameters);
    }

    /**
     * @param  array<string, mixed>  $parameters
     *
     * @throws ValidationException
     */
    private function enforceRepositoryLimit(array $parameters): void
    {
        $maxRepositories = (int) config('briefings.limits.max_repositories', 10);

        if ($maxRepositories <= 0) {
            return;
        }

        $repositoryIds = $parameters['repository_ids'] ?? null;

        if (! is_array($repositoryIds)) {
            return;
        }

        if (count($repositoryIds) > $maxRepositories) {
            throw ValidationException::withMessages([
                'repository_ids' => sprintf('You can select up to %d repositories for a briefing.', $maxRepositories),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     *
     * @throws ValidationException
     */
    private function enforceDateRangeLimit(array $parameters): void
    {
        $maxDays = (int) config('briefings.limits.max_date_range_days', 90);

        if ($maxDays <= 0) {
            return;
        }

        $end = isset($parameters['end_date'])
            ? Carbon::parse((string) $parameters['end_date'])
            : now();

        $start = isset($parameters['start_date'])
            ? Carbon::parse((string) $parameters['start_date'])
            : $end->copy()->subDays(7);

        if ($start->greaterThan($end)) {
            throw ValidationException::withMessages([
                'start_date' => 'Start date must be before end date.',
            ]);
        }

        $rangeDays = $start->diffInDays($end) + 1;

        if ($rangeDays > $maxDays) {
            throw ValidationException::withMessages([
                'end_date' => sprintf('Date range cannot exceed %d days.', $maxDays),
            ]);
        }
    }
}
