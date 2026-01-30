<?php

declare(strict_types=1);

use App\Enums\Briefings\BriefingDeliveryChannel;
use App\Enums\Briefings\BriefingSchedulePreset;
use App\Models\Briefing;
use App\Models\BriefingSubscription;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->plan = Plan::factory()->create([
        'features' => [
            'briefings' => [
                'enabled' => true,
                'scheduling_enabled' => true,
                'external_sharing_enabled' => true,
                'generations_per_month' => null,
            ],
        ],
    ]);
    $this->workspace = Workspace::factory()->create([
        'owner_id' => $this->user->id,
        'plan_id' => $this->plan->id,
    ]);

    $this->workspace->teamMembers()->create([
        'user_id' => $this->user->id,
        'team_id' => $this->workspace->team->id,
        'workspace_id' => $this->workspace->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $this->briefing = Briefing::factory()->system()->create([
        'slug' => 'standup-update',
        'title' => 'Daily Standup Update',
        'is_active' => true,
        'is_schedulable' => true,
    ]);
});

it('lists user subscriptions for workspace', function (): void {
    // Create different briefings for each subscription (unique constraint)
    $briefing2 = Briefing::factory()->system()->create();
    $briefing3 = Briefing::factory()->system()->create();

    BriefingSubscription::factory()
        ->forWorkspace($this->workspace)
        ->forUser($this->user)
        ->create(['briefing_id' => $this->briefing->id]);

    BriefingSubscription::factory()
        ->forWorkspace($this->workspace)
        ->forUser($this->user)
        ->create(['briefing_id' => $briefing2->id]);

    BriefingSubscription::factory()
        ->forWorkspace($this->workspace)
        ->forUser($this->user)
        ->create(['briefing_id' => $briefing3->id]);

    // Create subscription for another user - should not appear
    $otherUser = User::factory()->create();
    $briefing4 = Briefing::factory()->system()->create();
    BriefingSubscription::factory()
        ->forWorkspace($this->workspace)
        ->forUser($otherUser)
        ->create(['briefing_id' => $briefing4->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('briefing-subscriptions.index', $this->workspace));

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.workspace_id', $this->workspace->id)
        ->assertJsonPath('data.0.user_id', $this->user->id);
});

it('creates a subscription successfully', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('briefing-subscriptions.store', $this->workspace), [
            'briefing_id' => $this->briefing->id,
            'schedule_preset' => BriefingSchedulePreset::Weekly->value,
            'schedule_day' => 1,
            'schedule_hour' => 9,
            'delivery_channels' => [BriefingDeliveryChannel::Push->value],
            'parameters' => [],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.schedule_preset', BriefingSchedulePreset::Weekly->value)
        ->assertJsonStructure([
            'data' => ['id', 'schedule_preset', 'is_active', 'next_scheduled_at'],
            'message',
        ]);

    expect(BriefingSubscription::where('workspace_id', $this->workspace->id)->count())->toBe(1);
});

it('creates a subscription with slack delivery', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('briefing-subscriptions.store', $this->workspace), [
            'briefing_id' => $this->briefing->id,
            'schedule_preset' => BriefingSchedulePreset::Daily->value,
            'schedule_hour' => 10,
            'delivery_channels' => [
                BriefingDeliveryChannel::Push->value,
                BriefingDeliveryChannel::Slack->value,
            ],
            'slack_webhook_url' => 'https://hooks.slack.com/services/test',
            'parameters' => [],
        ]);

    $response->assertCreated();

    // Verify the subscription was created with Slack channel
    expect($response->json('data.delivery_channels'))->toContain(BriefingDeliveryChannel::Slack->value);

    // Verify the webhook URL was stored (slack_webhook_url is hidden from API responses for security)
    $subscription = BriefingSubscription::find($response->json('data.id'));
    expect($subscription->slack_webhook_url)->toBe('https://hooks.slack.com/services/test');
});

it('updates a subscription', function (): void {
    $subscription = BriefingSubscription::factory()
        ->forWorkspace($this->workspace)
        ->forUser($this->user)
        ->weekly()
        ->create(['briefing_id' => $this->briefing->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->patchJson(route('briefing-subscriptions.update', [$this->workspace, $subscription]), [
            'schedule_preset' => BriefingSchedulePreset::Daily->value,
            'schedule_hour' => 8,
            'is_active' => false,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.schedule_preset', BriefingSchedulePreset::Daily->value)
        ->assertJsonPath('data.schedule_hour', 8)
        ->assertJsonPath('data.is_active', false);
});

it('cancels a subscription by deactivating it', function (): void {
    $subscription = BriefingSubscription::factory()
        ->forWorkspace($this->workspace)
        ->forUser($this->user)
        ->create(['briefing_id' => $this->briefing->id, 'is_active' => true]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->deleteJson(route('briefing-subscriptions.destroy', [$this->workspace, $subscription]));

    $response->assertOk()
        ->assertJsonPath('message', 'Subscription cancelled successfully.');

    // CancelBriefingSubscription deactivates rather than deletes
    $subscription->refresh();
    expect($subscription->is_active)->toBeFalse();
});

it('requires authentication to create subscription', function (): void {
    $response = $this->postJson(route('briefing-subscriptions.store', $this->workspace), [
        'briefing_id' => $this->briefing->id,
        'schedule_preset' => BriefingSchedulePreset::Daily->value,
        'delivery_channels' => [BriefingDeliveryChannel::Push->value],
    ]);

    $response->assertUnauthorized();
});

it('requires workspace membership to create subscription', function (): void {
    $otherUser = User::factory()->create();

    $response = $this->actingAs($otherUser, 'sanctum')
        ->postJson(route('briefing-subscriptions.store', $this->workspace), [
            'briefing_id' => $this->briefing->id,
            'schedule_preset' => BriefingSchedulePreset::Daily->value,
            'delivery_channels' => [BriefingDeliveryChannel::Push->value],
        ]);

    $response->assertForbidden();
});

it('returns 404 when updating subscription from another workspace', function (): void {
    $otherWorkspace = Workspace::factory()->create();
    $subscription = BriefingSubscription::factory()
        ->forWorkspace($otherWorkspace)
        ->forUser($this->user)
        ->create(['briefing_id' => $this->briefing->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->patchJson(route('briefing-subscriptions.update', [$this->workspace, $subscription]), [
            'is_active' => false,
        ]);

    $response->assertNotFound();
});

it('validates required fields for subscription creation', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('briefing-subscriptions.store', $this->workspace), []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['briefing_id', 'schedule_preset']);
});

it('validates schedule_preset is valid enum', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('briefing-subscriptions.store', $this->workspace), [
            'briefing_id' => $this->briefing->id,
            'schedule_preset' => 'invalid-preset',
            'delivery_channels' => [BriefingDeliveryChannel::Push->value],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['schedule_preset']);
});
