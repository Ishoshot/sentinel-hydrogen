<?php

declare(strict_types=1);

use App\Actions\Briefings\CreateBriefingSubscription;
use App\Enums\Briefings\BriefingDeliveryChannel;
use App\Enums\Briefings\BriefingSchedulePreset;
use App\Models\Briefing;
use App\Models\BriefingSubscription;
use App\Models\Plan;
use App\Models\SlackIntegration;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Briefings\ValueObjects\BriefingDeliveryChannels;
use App\Services\Briefings\ValueObjects\BriefingParameters;
use Illuminate\Validation\ValidationException;

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

it('creates a subscription with slack delivery when workspace has slack integration', function (): void {
    SlackIntegration::factory()
        ->forWorkspace($this->workspace)
        ->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('briefing-subscriptions.store', $this->workspace), [
            'briefing_id' => $this->briefing->id,
            'schedule_preset' => BriefingSchedulePreset::Daily->value,
            'schedule_hour' => 10,
            'delivery_channels' => [
                BriefingDeliveryChannel::Push->value,
                BriefingDeliveryChannel::Slack->value,
            ],
            'parameters' => [],
        ]);

    $response->assertCreated();

    expect($response->json('data.delivery_channels'))->toContain(BriefingDeliveryChannel::Slack->value);
});

it('cannot select slack channel when workspace has no slack integration', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('briefing-subscriptions.store', $this->workspace), [
            'briefing_id' => $this->briefing->id,
            'schedule_preset' => BriefingSchedulePreset::Daily->value,
            'schedule_hour' => 10,
            'delivery_channels' => [
                BriefingDeliveryChannel::Push->value,
                BriefingDeliveryChannel::Slack->value,
            ],
            'parameters' => [],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['delivery_channels']);
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

it('rejects invalid briefing parameters on subscription creation', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('briefing-subscriptions.store', $this->workspace), [
            'briefing_id' => $this->briefing->id,
            'schedule_preset' => BriefingSchedulePreset::Daily->value,
            'schedule_hour' => 9,
            'delivery_channels' => [BriefingDeliveryChannel::Push->value],
            'parameters' => [
                'start_date' => 'not-a-date',
            ],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['start_date']);
});

it('rejects invalid briefing parameters on subscription update', function (): void {
    $subscription = BriefingSubscription::factory()
        ->forWorkspace($this->workspace)
        ->forUser($this->user)
        ->daily()
        ->create([
            'briefing_id' => $this->briefing->id,
            'parameters' => [],
        ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->patchJson(route('briefing-subscriptions.update', [$this->workspace, $subscription]), [
            'parameters' => [
                'start_date' => 'not-a-date',
            ],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['start_date']);
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

it('rejects duplicate subscription for same user and briefing in workspace', function (): void {
    BriefingSubscription::factory()
        ->forWorkspace($this->workspace)
        ->forUser($this->user)
        ->create(['briefing_id' => $this->briefing->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('briefing-subscriptions.store', $this->workspace), [
            'briefing_id' => $this->briefing->id,
            'schedule_preset' => BriefingSchedulePreset::Daily->value,
            'schedule_hour' => 9,
            'delivery_channels' => [BriefingDeliveryChannel::Push->value],
            'parameters' => [],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['briefing_id']);
});

it('enforces schedulable guard inside create subscription action', function (): void {
    $action = app(CreateBriefingSubscription::class);
    $unschedulableBriefing = Briefing::factory()->system()->notSchedulable()->create();

    expect(fn (): BriefingSubscription => $action->handle(
        workspace: $this->workspace,
        user: $this->user,
        briefing: $unschedulableBriefing,
        schedulePreset: BriefingSchedulePreset::Daily,
        deliveryChannels: BriefingDeliveryChannels::fromStrings([BriefingDeliveryChannel::Push->value]),
        parameters: BriefingParameters::fromArray([]),
        scheduleHour: 9,
    ))->toThrow(ValidationException::class);
});

it('rejects schedule_day above 7 for weekly preset', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('briefing-subscriptions.store', $this->workspace), [
            'briefing_id' => $this->briefing->id,
            'schedule_preset' => BriefingSchedulePreset::Weekly->value,
            'schedule_day' => 8,
            'schedule_hour' => 9,
            'delivery_channels' => [BriefingDeliveryChannel::Push->value],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['schedule_day']);
});

it('rejects schedule_day above 28 for monthly preset', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('briefing-subscriptions.store', $this->workspace), [
            'briefing_id' => $this->briefing->id,
            'schedule_preset' => BriefingSchedulePreset::Monthly->value,
            'schedule_day' => 29,
            'schedule_hour' => 9,
            'delivery_channels' => [BriefingDeliveryChannel::Push->value],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['schedule_day']);
});

it('accepts null schedule_day for daily preset', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('briefing-subscriptions.store', $this->workspace), [
            'briefing_id' => $this->briefing->id,
            'schedule_preset' => BriefingSchedulePreset::Daily->value,
            'schedule_day' => null,
            'schedule_hour' => 9,
            'delivery_channels' => [BriefingDeliveryChannel::Push->value],
            'parameters' => [],
        ]);

    $response->assertCreated();
});

it('requires schedule_day for weekly preset', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('briefing-subscriptions.store', $this->workspace), [
            'briefing_id' => $this->briefing->id,
            'schedule_preset' => BriefingSchedulePreset::Weekly->value,
            'schedule_hour' => 9,
            'delivery_channels' => [BriefingDeliveryChannel::Push->value],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['schedule_day']);
});

it('requires schedule_day for monthly preset', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('briefing-subscriptions.store', $this->workspace), [
            'briefing_id' => $this->briefing->id,
            'schedule_preset' => BriefingSchedulePreset::Monthly->value,
            'schedule_hour' => 9,
            'delivery_channels' => [BriefingDeliveryChannel::Push->value],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['schedule_day']);
});

it('rejects schedule_hour above 23', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('briefing-subscriptions.store', $this->workspace), [
            'briefing_id' => $this->briefing->id,
            'schedule_preset' => BriefingSchedulePreset::Daily->value,
            'schedule_hour' => 24,
            'delivery_channels' => [BriefingDeliveryChannel::Push->value],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['schedule_hour']);
});

it('rejects schedule_day above 7 for weekly preset on update', function (): void {
    $subscription = BriefingSubscription::factory()
        ->forWorkspace($this->workspace)
        ->forUser($this->user)
        ->weekly()
        ->create(['briefing_id' => $this->briefing->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->patchJson(route('briefing-subscriptions.update', [$this->workspace, $subscription]), [
            'schedule_preset' => BriefingSchedulePreset::Weekly->value,
            'schedule_day' => 8,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['schedule_day']);
});

it('rejects schedule_day above 28 for monthly preset on update', function (): void {
    $subscription = BriefingSubscription::factory()
        ->forWorkspace($this->workspace)
        ->forUser($this->user)
        ->monthly()
        ->create(['briefing_id' => $this->briefing->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->patchJson(route('briefing-subscriptions.update', [$this->workspace, $subscription]), [
            'schedule_preset' => BriefingSchedulePreset::Monthly->value,
            'schedule_day' => 29,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['schedule_day']);
});

it('calculates next scheduled at for daily preset', function (): void {
    $subscription = BriefingSubscription::factory()
        ->daily()
        ->make(['schedule_hour' => 14]);

    $next = $subscription->calculateNextScheduledAt();

    expect($next->hour)->toBe(14)
        ->and($next->minute)->toBe(0)
        ->and($next->isAfter(now()))->toBeTrue();
});

it('calculates next scheduled at for weekly preset with correct day', function (): void {
    $subscription = BriefingSubscription::factory()
        ->weekly(3) // Wednesday (ISO)
        ->make(['schedule_hour' => 10]);

    $next = $subscription->calculateNextScheduledAt();

    // ISO day 3 = Wednesday
    expect($next->dayOfWeekIso)->toBe(3)
        ->and($next->hour)->toBe(10)
        ->and($next->minute)->toBe(0)
        ->and($next->isAfter(now()))->toBeTrue();
});

it('calculates next scheduled at for weekly preset with Sunday (ISO day 7)', function (): void {
    $subscription = BriefingSubscription::factory()
        ->weekly(7) // Sunday (ISO)
        ->make(['schedule_hour' => 8]);

    $next = $subscription->calculateNextScheduledAt();

    // ISO day 7 = Sunday
    expect($next->dayOfWeekIso)->toBe(7)
        ->and($next->hour)->toBe(8)
        ->and($next->minute)->toBe(0)
        ->and($next->isAfter(now()))->toBeTrue();
});

it('calculates next scheduled at for monthly preset', function (): void {
    $subscription = BriefingSubscription::factory()
        ->monthly(15)
        ->make(['schedule_hour' => 12]);

    $next = $subscription->calculateNextScheduledAt();

    expect($next->day)->toBe(15)
        ->and($next->hour)->toBe(12)
        ->and($next->minute)->toBe(0)
        ->and($next->isAfter(now()))->toBeTrue();
});

it('applies jitter within 0-5 minute range', function (): void {
    $subscription = BriefingSubscription::factory()
        ->daily()
        ->make(['schedule_hour' => 9]);

    $results = collect(range(1, 50))->map(fn () => $subscription->calculateNextScheduledAt(withJitter: true)->minute);

    expect($results->min())->toBeGreaterThanOrEqual(0)
        ->and($results->max())->toBeLessThanOrEqual(5);
});

it('does not apply jitter without flag', function (): void {
    $subscription = BriefingSubscription::factory()
        ->daily()
        ->make(['schedule_hour' => 9]);

    $next = $subscription->calculateNextScheduledAt();

    expect($next->minute)->toBe(0);
});
