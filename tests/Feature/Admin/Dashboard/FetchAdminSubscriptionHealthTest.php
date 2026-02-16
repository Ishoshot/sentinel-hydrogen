<?php

declare(strict_types=1);

use App\Actions\Admin\Dashboard\FetchAdminSubscriptionHealth;
use App\Actions\Admin\Dashboard\ValueObjects\AdminDashboardFilters;
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

it('returns subscription health counts for scoped workspaces', function (): void {
    $plan = Plan::factory()->create();

    $active = Workspace::factory()->create([
        'plan_id' => $plan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $trialing = Workspace::factory()->create([
        'plan_id' => $plan->id,
        'subscription_status' => SubscriptionStatus::Trialing,
        'trial_ends_at' => '2026-02-20 09:00:00',
    ]);

    $pastDue = Workspace::factory()->create([
        'plan_id' => $plan->id,
        'subscription_status' => SubscriptionStatus::PastDue,
    ]);

    Workspace::factory()->create([
        'plan_id' => $plan->id,
        'subscription_status' => SubscriptionStatus::Canceled,
    ]);

    Subscription::factory()->create([
        'workspace_id' => $active->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'current_period_end' => '2026-02-21 09:00:00',
    ]);

    Subscription::factory()->create([
        'workspace_id' => $trialing->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Trialing,
        'current_period_end' => '2026-02-19 09:00:00',
    ]);

    Subscription::factory()->create([
        'workspace_id' => $pastDue->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::PastDue,
        'current_period_end' => '2026-02-19 09:00:00',
    ]);

    $health = app(FetchAdminSubscriptionHealth::class)->handle(AdminDashboardFilters::fromArray([]));

    expect($health['active_or_trialing'])->toBe(2)
        ->and($health['past_due'])->toBe(1)
        ->and($health['canceled_or_revoked'])->toBe(1)
        ->and($health['renewals_due_next_7_days'])->toBe(2)
        ->and($health['trials_ending_next_7_days'])->toBe(1);
});

it('applies workspace filter to health counters', function (): void {
    $plan = Plan::factory()->create();

    $workspace = Workspace::factory()->create([
        'plan_id' => $plan->id,
        'subscription_status' => SubscriptionStatus::PastDue,
    ]);

    Workspace::factory()->create([
        'plan_id' => $plan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $filters = AdminDashboardFilters::fromArray([
        'workspace_id' => (string) $workspace->id,
    ]);

    $health = app(FetchAdminSubscriptionHealth::class)->handle($filters);

    expect($health['active_or_trialing'])->toBe(0)
        ->and($health['past_due'])->toBe(1);
});
