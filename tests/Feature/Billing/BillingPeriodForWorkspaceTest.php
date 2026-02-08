<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\Plans\ValueObjects\BillingPeriod;
use Carbon\CarbonImmutable;

it('resolves billing period from workspace subscription', function (): void {
    $workspace = Workspace::factory()->create();
    $periodStart = CarbonImmutable::parse('2026-01-15');
    $periodEnd = CarbonImmutable::parse('2026-02-14');

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'current_period_start' => $periodStart,
        'current_period_end' => $periodEnd,
    ]);

    $period = BillingPeriod::forWorkspace($workspace);

    expect($period->start->toDateString())->toBe('2026-01-15')
        ->and($period->end->toDateString())->toBe('2026-02-14');
});

it('falls back to calendar month when workspace has no subscription', function (): void {
    $workspace = Workspace::factory()->create();

    $period = BillingPeriod::forWorkspace($workspace);
    $now = CarbonImmutable::now();

    expect($period->start->toDateString())->toBe($now->startOfMonth()->toDateString())
        ->and($period->end->toDateString())->toBe($now->endOfMonth()->toDateString());
});

it('falls back to calendar month when subscription has no period dates', function (): void {
    $workspace = Workspace::factory()->create();

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'current_period_start' => null,
        'current_period_end' => null,
    ]);

    $period = BillingPeriod::forWorkspace($workspace);
    $now = CarbonImmutable::now();

    expect($period->start->toDateString())->toBe($now->startOfMonth()->toDateString())
        ->and($period->end->toDateString())->toBe($now->endOfMonth()->toDateString());
});

it('uses the latest subscription when workspace has multiple', function (): void {
    $workspace = Workspace::factory()->create();

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'current_period_start' => CarbonImmutable::parse('2026-01-01'),
        'current_period_end' => CarbonImmutable::parse('2026-01-31'),
        'created_at' => now()->subDays(30),
    ]);

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'current_period_start' => CarbonImmutable::parse('2026-02-01'),
        'current_period_end' => CarbonImmutable::parse('2026-02-28'),
        'created_at' => now(),
    ]);

    $period = BillingPeriod::forWorkspace($workspace);

    expect($period->start->toDateString())->toBe('2026-02-01')
        ->and($period->end->toDateString())->toBe('2026-02-28');
});
