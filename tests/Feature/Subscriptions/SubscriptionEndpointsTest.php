<?php

declare(strict_types=1);

use App\Enums\PlanTier;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;

it('shows the current workspace subscription', function (): void {
    $user = User::factory()->create();
    $plan = Plan::factory()->create(['tier' => PlanTier::Foundation->value]);
    $workspace = Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $plan->id,
        'subscription_status' => App\Enums\SubscriptionStatus::Active,
    ]);

    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'workspace_id' => $workspace->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('subscriptions.show', $workspace));

    $response->assertOk()
        ->assertJsonPath('data.plan.tier', PlanTier::Foundation->value);
});

it('allows owners to upgrade subscription when Polar is not configured', function (): void {
    config()->set('services.polar.access_token', null);

    $user = User::factory()->create();
    $foundationPlan = Plan::factory()->create(['tier' => PlanTier::Foundation->value]);
    $illuminatePlan = Plan::factory()->illuminate()->create();

    $workspace = Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $foundationPlan->id,
        'subscription_status' => App\Enums\SubscriptionStatus::Active,
    ]);

    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'workspace_id' => $workspace->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('subscriptions.upgrade', $workspace), [
            'plan_tier' => PlanTier::Illuminate->value,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.plan.tier', $illuminatePlan->tier);
});

it('rejects invalid promo codes', function (): void {
    config()->set('services.polar.access_token', null);

    $user = User::factory()->create();
    $foundationPlan = Plan::factory()->create(['tier' => PlanTier::Foundation->value]);
    Plan::factory()->illuminate()->create();

    $workspace = Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $foundationPlan->id,
        'subscription_status' => App\Enums\SubscriptionStatus::Active,
    ]);

    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'workspace_id' => $workspace->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('subscriptions.upgrade', $workspace), [
            'plan_tier' => PlanTier::Illuminate->value,
            'promo_code' => 'INVALID-CODE',
        ]);

    $response->assertUnprocessable()
        ->assertJsonPath('errors.promo_code.0', 'Invalid promotion code.');
});

it('prevents non-owners from upgrading subscriptions', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $foundationPlan = Plan::factory()->create(['tier' => PlanTier::Foundation->value]);

    $workspace = Workspace::factory()->create([
        'owner_id' => $owner->id,
        'plan_id' => $foundationPlan->id,
        'subscription_status' => App\Enums\SubscriptionStatus::Active,
    ]);

    $workspace->teamMembers()->create([
        'user_id' => $member->id,
        'team_id' => $workspace->team->id,
        'workspace_id' => $workspace->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($member, 'sanctum')
        ->postJson(route('subscriptions.upgrade', $workspace), [
            'plan_tier' => PlanTier::Illuminate->value,
        ]);

    $response->assertForbidden();
});
