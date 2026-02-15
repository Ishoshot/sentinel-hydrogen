<?php

declare(strict_types=1);

use App\Services\Briefings\Policies\BriefingParameterLimitPolicy;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

it('throws when repository count exceeds configured maximum', function (): void {
    config()->set('briefings.limits.max_repositories', 1);

    $enforcer = app(BriefingParameterLimitPolicy::class);

    try {
        $enforcer->enforce(['repository_ids' => [1, 2]]);
        $this->fail('Expected validation exception was not thrown.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['repository_ids'][0] ?? null)
            ->toBe('You can select up to 1 repositories for a briefing.');
    }
});

it('throws when start date is after end date', function (): void {
    config()->set('briefings.limits.max_date_range_days', 90);

    $enforcer = app(BriefingParameterLimitPolicy::class);

    try {
        $enforcer->enforce([
            'start_date' => '2026-02-14',
            'end_date' => '2026-02-01',
        ]);
        $this->fail('Expected validation exception was not thrown.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['start_date'][0] ?? null)
            ->toBe('Start date must be before end date.');
    }
});

it('throws when date range exceeds configured maximum', function (): void {
    config()->set('briefings.limits.max_date_range_days', 7);

    $enforcer = app(BriefingParameterLimitPolicy::class);

    try {
        $enforcer->enforce([
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-12',
        ]);
        $this->fail('Expected validation exception was not thrown.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['end_date'][0] ?? null)
            ->toBe('Date range cannot exceed 7 days.');
    }
});

it('passes for valid parameters within configured limits', function (): void {
    Carbon::setTestNow('2026-02-14 09:00:00');
    config()->set('briefings.limits.max_repositories', 3);
    config()->set('briefings.limits.max_date_range_days', 14);

    $enforcer = app(BriefingParameterLimitPolicy::class);

    expect(fn () => $enforcer->enforce([
        'repository_ids' => [1, 2],
        'start_date' => '2026-02-05',
        'end_date' => '2026-02-14',
    ]))->not->toThrow(ValidationException::class);

    Carbon::setTestNow();
});
