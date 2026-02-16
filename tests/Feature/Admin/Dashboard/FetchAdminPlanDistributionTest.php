<?php

declare(strict_types=1);

use App\Actions\Admin\Dashboard\FetchAdminPlanDistribution;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Enums\Billing\PlanTier;
use App\Models\Plan;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-02-16 10:00:00');
    Cache::flush();
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('returns plan distribution counts in plan-tier order', function (): void {
    $foundation = Plan::factory()->create(['tier' => PlanTier::Foundation->value]);
    $illuminate = Plan::factory()->create(['tier' => PlanTier::Illuminate->value]);
    $orchestrate = Plan::factory()->create(['tier' => PlanTier::Orchestrate->value]);
    $sanctum = Plan::factory()->create(['tier' => PlanTier::Sanctum->value]);

    Workspace::factory()->count(2)->create(['plan_id' => $foundation->id]);
    Workspace::factory()->count(1)->create(['plan_id' => $illuminate->id]);
    Workspace::factory()->count(3)->create(['plan_id' => $orchestrate->id]);
    Workspace::factory()->count(1)->create(['plan_id' => $sanctum->id]);

    $filters = AdminDashboardFilters::fromArray([]);

    $data = app(FetchAdminPlanDistribution::class)->handle($filters);

    expect($data['labels'])->toBe(['Foundation', 'Illuminate', 'Orchestrate', 'Sanctum'])
        ->and($data['datasets'][0]['data'])->toBe([2, 1, 3, 1]);
});

it('applies plan tier filter to distribution data', function (): void {
    $foundation = Plan::factory()->create(['tier' => PlanTier::Foundation->value]);
    $sanctum = Plan::factory()->create(['tier' => PlanTier::Sanctum->value]);

    Workspace::factory()->count(2)->create(['plan_id' => $foundation->id]);
    Workspace::factory()->count(1)->create(['plan_id' => $sanctum->id]);

    $filters = AdminDashboardFilters::fromArray([
        'plan_tier' => PlanTier::Sanctum->value,
    ]);

    $data = app(FetchAdminPlanDistribution::class)->handle($filters);

    expect($data['datasets'][0]['data'])->toBe([0, 0, 0, 1]);
});
