<?php

declare(strict_types=1);

use App\Actions\Admin\Dashboard\FetchAdminAtRiskWorkspaces;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
use App\Enums\Billing\PlanTier;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
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

it('returns at-risk workspaces with mapped risk reasons', function (): void {
    $foundation = Plan::factory()->create(['tier' => PlanTier::Foundation->value]);

    $pastDue = Workspace::factory()->create([
        'name' => 'Past Due Co',
        'plan_id' => $foundation->id,
        'subscription_status' => SubscriptionStatus::PastDue,
    ]);

    $trialEnding = Workspace::factory()->create([
        'name' => 'Trial Co',
        'plan_id' => $foundation->id,
        'subscription_status' => SubscriptionStatus::Trialing,
        'trial_ends_at' => '2026-02-21 09:00:00',
    ]);

    $renewalDue = Workspace::factory()->create([
        'name' => 'Renewal Co',
        'plan_id' => $foundation->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    Workspace::factory()->create([
        'name' => 'Healthy Co',
        'plan_id' => $foundation->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    Subscription::factory()->create([
        'workspace_id' => $renewalDue->id,
        'plan_id' => $foundation->id,
        'status' => SubscriptionStatus::Active,
        'current_period_end' => '2026-02-22 09:00:00',
    ]);

    $rows = app(FetchAdminAtRiskWorkspaces::class)->handle(AdminDashboardFilters::fromArray([]));

    expect($rows)->toHaveCount(3)
        ->and($rows[0]['workspace_name'])->toBe($pastDue->name)
        ->and(collect($rows)->pluck('risk_reason')->all())->toContain('Past due billing', 'Trial ending soon', 'Renewal due soon');
});

it('applies plan tier filter to at-risk list', function (): void {
    $foundation = Plan::factory()->create(['tier' => PlanTier::Foundation->value]);
    $sanctum = Plan::factory()->create(['tier' => PlanTier::Sanctum->value]);

    Workspace::factory()->create([
        'plan_id' => $foundation->id,
        'subscription_status' => SubscriptionStatus::PastDue,
    ]);

    Workspace::factory()->create([
        'plan_id' => $sanctum->id,
        'subscription_status' => SubscriptionStatus::PastDue,
    ]);

    $rows = app(FetchAdminAtRiskWorkspaces::class)->handle(AdminDashboardFilters::fromArray([
        'plan_tier' => PlanTier::Sanctum->value,
    ]));

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['plan_tier'])->toBe('Sanctum');
});
