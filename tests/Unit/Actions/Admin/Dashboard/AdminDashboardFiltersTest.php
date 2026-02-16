<?php

declare(strict_types=1);

use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Enums\Billing\PlanTier;
use App\Enums\Reviews\RunStatus;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-02-16 12:00:00');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('uses sensible defaults when filters are empty', function (): void {
    $filters = AdminDashboardFilters::fromArray([]);

    expect($filters->workspaceId)->toBeNull()
        ->and($filters->planTier)->toBeNull()
        ->and($filters->runStatus)->toBeNull()
        ->and($filters->totalDays())->toBe(14)
        ->and($filters->startDate->toDateString())->toBe('2026-02-03')
        ->and($filters->endDate->toDateString())->toBe('2026-02-16');
});

it('parses valid scalar and enum filter values', function (): void {
    $filters = AdminDashboardFilters::fromArray([
        'start_date' => '2026-02-01',
        'end_date' => '2026-02-10',
        'workspace_id' => '42',
        'plan_tier' => PlanTier::Sanctum->value,
        'run_status' => RunStatus::Failed->value,
    ]);

    expect($filters->workspaceId)->toBe(42)
        ->and($filters->planTier)->toBe(PlanTier::Sanctum)
        ->and($filters->runStatus)->toBe(RunStatus::Failed)
        ->and($filters->totalDays())->toBe(10);
});

it('normalizes invalid and oversized ranges', function (): void {
    $filters = AdminDashboardFilters::fromArray([
        'start_date' => 'not-a-date',
        'end_date' => '2099-01-01',
        'workspace_id' => '-7',
        'plan_tier' => 'unknown',
        'run_status' => 'wrong',
    ]);

    expect($filters->workspaceId)->toBeNull()
        ->and($filters->planTier)->toBeNull()
        ->and($filters->runStatus)->toBeNull()
        ->and($filters->endDate->toDateString())->toBe('2026-02-16')
        ->and($filters->totalDays())->toBe(14);

    $clamped = AdminDashboardFilters::fromArray([
        'start_date' => '2025-01-01',
        'end_date' => '2026-02-16',
    ]);

    expect($clamped->totalDays())->toBe(120)
        ->and($clamped->startDate->toDateString())->toBe('2025-10-20');
});

it('calculates a matching previous comparison window', function (): void {
    $filters = AdminDashboardFilters::fromArray([
        'start_date' => '2026-02-10',
        'end_date' => '2026-02-16',
    ]);

    $previousRange = $filters->previousRange();

    expect($previousRange['start']->toDateString())->toBe('2026-02-03')
        ->and($previousRange['end']->toDateString())->toBe('2026-02-09')
        ->and((int) $previousRange['start']->diffInDays($previousRange['end']) + 1)->toBe($filters->totalDays());
});
