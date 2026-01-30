<?php

declare(strict_types=1);

use App\Enums\Billing\PlanTier;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;

it('shows the current workspace subscription', function (): void {
    $user = User::factory()->create();
    $plan = Plan::factory()->create(['tier' => PlanTier::Foundation->value]);
    $workspace = Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $plan->id,
        'subscription_status' => SubscriptionStatus::Active,
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

it('subscribes from free to paid tier without Polar', function (): void {
    config()->set('services.polar.access_token', null);

    $user = User::factory()->create();
    $foundationPlan = Plan::factory()->create(['tier' => PlanTier::Foundation->value]);
    $illuminatePlan = Plan::factory()->illuminate()->create();

    $workspace = Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $foundationPlan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'workspace_id' => $workspace->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('subscriptions.change', $workspace), [
            'plan_tier' => PlanTier::Illuminate->value,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.plan.tier', $illuminatePlan->tier);

    expect($workspace->refresh()->plan_id)->toBe($illuminatePlan->id)
        ->and($workspace->subscription_status)->toBe(SubscriptionStatus::Active);
});

it('upgrades from paid to higher paid tier without Polar', function (): void {
    config()->set('services.polar.access_token', null);

    $user = User::factory()->create();
    $illuminatePlan = Plan::factory()->illuminate()->create();
    $orchestratePlan = Plan::factory()->orchestrate()->create();

    $workspace = Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $illuminatePlan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'workspace_id' => $workspace->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('subscriptions.change', $workspace), [
            'plan_tier' => PlanTier::Orchestrate->value,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.plan.tier', $orchestratePlan->tier);

    expect($workspace->refresh()->plan_id)->toBe($orchestratePlan->id);
});

it('downgrades from paid to lower paid tier without Polar', function (): void {
    config()->set('services.polar.access_token', null);

    $user = User::factory()->create();
    $illuminatePlan = Plan::factory()->illuminate()->create();
    $orchestratePlan = Plan::factory()->orchestrate()->create();

    $workspace = Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $orchestratePlan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'workspace_id' => $workspace->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('subscriptions.change', $workspace), [
            'plan_tier' => PlanTier::Illuminate->value,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.plan.tier', $illuminatePlan->tier);

    expect($workspace->refresh()->plan_id)->toBe($illuminatePlan->id)
        ->and($workspace->subscription_status)->toBe(SubscriptionStatus::Active);
});

it('cancels paid subscription to foundation without Polar', function (): void {
    config()->set('services.polar.access_token', null);

    $user = User::factory()->create();
    $illuminatePlan = Plan::factory()->illuminate()->create();

    $workspace = Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $illuminatePlan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'workspace_id' => $workspace->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('subscriptions.change', $workspace), [
            'plan_tier' => PlanTier::Foundation->value,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.plan.tier', PlanTier::Foundation->value);

    expect($workspace->refresh()->subscription_status)->toBe(SubscriptionStatus::Canceled);
});

it('rejects change to the same tier', function (): void {
    config()->set('services.polar.access_token', null);

    $user = User::factory()->create();
    $illuminatePlan = Plan::factory()->illuminate()->create();

    $workspace = Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $illuminatePlan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'workspace_id' => $workspace->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('subscriptions.change', $workspace), [
            'plan_tier' => PlanTier::Illuminate->value,
        ]);

    $response->assertUnprocessable();
});

it('returns checkout url when Polar is configured for subscribe', function (): void {
    config()->set('services.polar.access_token', 'test-token');
    config()->set('services.polar.api_url', 'https://api.polar.sh');
    config()->set('services.polar.product_ids', [
        'monthly' => ['illuminate' => 'prod_illuminate_monthly'],
        'yearly' => ['illuminate' => 'prod_illuminate_yearly'],
    ]);
    config()->set('app.frontend_url', 'https://app.sentinel.test');

    Http::fake([
        'https://api.polar.sh/v1/checkouts' => Http::response([
            'url' => 'https://checkout.polar.sh/test-session',
        ]),
    ]);

    $user = User::factory()->create();
    $foundationPlan = Plan::factory()->create(['tier' => PlanTier::Foundation->value]);
    Plan::factory()->illuminate()->create();

    $workspace = Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $foundationPlan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'workspace_id' => $workspace->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('subscriptions.change', $workspace), [
            'plan_tier' => PlanTier::Illuminate->value,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.checkout_url', 'https://checkout.polar.sh/test-session')
        ->assertJsonPath('data.billing_interval', 'monthly');

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), '/v1/checkouts')
            && str_contains($request['success_url'], '/billing/success?checkout_id=');
    });
});

it('updates Polar subscription on upgrade with existing polar_subscription_id', function (): void {
    config()->set('services.polar.access_token', 'test-token');
    config()->set('services.polar.api_url', 'https://api.polar.sh');
    config()->set('services.polar.product_ids', [
        'monthly' => [
            'illuminate' => 'prod_illuminate_monthly',
            'orchestrate' => 'prod_orchestrate_monthly',
        ],
        'yearly' => [],
    ]);

    Http::fake([
        'https://api.polar.sh/v1/subscriptions/*' => Http::response(['id' => 'sub_123']),
    ]);

    $user = User::factory()->create();
    $illuminatePlan = Plan::factory()->illuminate()->create();
    $orchestratePlan = Plan::factory()->orchestrate()->create();

    $workspace = Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $illuminatePlan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $illuminatePlan->id,
        'polar_subscription_id' => 'sub_123',
        'polar_customer_id' => 'cust_123',
    ]);

    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'workspace_id' => $workspace->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('subscriptions.change', $workspace), [
            'plan_tier' => PlanTier::Orchestrate->value,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.plan.tier', $orchestratePlan->tier);

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), '/v1/subscriptions/sub_123')
            && $request->method() === 'PATCH'
            && $request['product_id'] === 'prod_orchestrate_monthly';
    });
});

it('updates Polar subscription on downgrade with existing polar_subscription_id', function (): void {
    config()->set('services.polar.access_token', 'test-token');
    config()->set('services.polar.api_url', 'https://api.polar.sh');
    config()->set('services.polar.product_ids', [
        'monthly' => [
            'illuminate' => 'prod_illuminate_monthly',
            'orchestrate' => 'prod_orchestrate_monthly',
        ],
        'yearly' => [],
    ]);

    Http::fake([
        'https://api.polar.sh/v1/subscriptions/*' => Http::response(['id' => 'sub_456']),
    ]);

    $user = User::factory()->create();
    $illuminatePlan = Plan::factory()->illuminate()->create();
    $orchestratePlan = Plan::factory()->orchestrate()->create();

    $workspace = Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $orchestratePlan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $orchestratePlan->id,
        'polar_subscription_id' => 'sub_456',
        'polar_customer_id' => 'cust_456',
    ]);

    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'workspace_id' => $workspace->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('subscriptions.change', $workspace), [
            'plan_tier' => PlanTier::Illuminate->value,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.plan.tier', $illuminatePlan->tier);

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), '/v1/subscriptions/sub_456')
            && $request->method() === 'PATCH'
            && $request['product_id'] === 'prod_illuminate_monthly';
    });
});

it('revokes Polar subscription on cancel with existing polar_subscription_id', function (): void {
    config()->set('services.polar.access_token', 'test-token');
    config()->set('services.polar.api_url', 'https://api.polar.sh');
    config()->set('services.polar.product_ids', [
        'monthly' => ['illuminate' => 'prod_illuminate_monthly'],
        'yearly' => [],
    ]);

    Http::fake([
        'https://api.polar.sh/v1/subscriptions/*' => Http::response([], 204),
    ]);

    $user = User::factory()->create();
    $illuminatePlan = Plan::factory()->illuminate()->create();

    $workspace = Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $illuminatePlan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $illuminatePlan->id,
        'polar_subscription_id' => 'sub_789',
        'polar_customer_id' => 'cust_789',
    ]);

    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'workspace_id' => $workspace->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('subscriptions.change', $workspace), [
            'plan_tier' => PlanTier::Foundation->value,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.plan.tier', PlanTier::Foundation->value);

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), '/v1/subscriptions/sub_789')
            && $request->method() === 'DELETE';
    });

    expect($workspace->refresh()->subscription_status)->toBe(SubscriptionStatus::Canceled);
});

it('rejects invalid promo codes', function (): void {
    config()->set('services.polar.access_token', null);

    $user = User::factory()->create();
    $foundationPlan = Plan::factory()->create(['tier' => PlanTier::Foundation->value]);
    Plan::factory()->illuminate()->create();

    $workspace = Workspace::factory()->create([
        'owner_id' => $user->id,
        'plan_id' => $foundationPlan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $workspace->teamMembers()->create([
        'user_id' => $user->id,
        'team_id' => $workspace->team->id,
        'workspace_id' => $workspace->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('subscriptions.change', $workspace), [
            'plan_tier' => PlanTier::Illuminate->value,
            'promo_code' => 'INVALID-CODE',
        ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Invalid promotion code.');
});

it('prevents non-owners from changing subscriptions', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $foundationPlan = Plan::factory()->create(['tier' => PlanTier::Foundation->value]);

    $workspace = Workspace::factory()->create([
        'owner_id' => $owner->id,
        'plan_id' => $foundationPlan->id,
        'subscription_status' => SubscriptionStatus::Active,
    ]);

    $workspace->teamMembers()->create([
        'user_id' => $member->id,
        'team_id' => $workspace->team->id,
        'workspace_id' => $workspace->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($member, 'sanctum')
        ->postJson(route('subscriptions.change', $workspace), [
            'plan_tier' => PlanTier::Illuminate->value,
        ]);

    $response->assertForbidden();
});
