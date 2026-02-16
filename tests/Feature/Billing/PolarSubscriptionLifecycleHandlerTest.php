<?php

declare(strict_types=1);

use App\Actions\Billing\Handlers\PolarSubscriptionLifecycleHandler;
use App\Enums\Billing\PlanTier;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\Webhooks\PolarWebhookEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\ValueObjects\VerifiedPolarWebhook;

it('handles canceled subscription lifecycle', function (): void {
    $subscription = Subscription::factory()->create([
        'polar_subscription_id' => 'sub_cancel',
        'status' => SubscriptionStatus::Active,
    ]);

    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionCanceled,
        data: [
            'id' => 'sub_cancel',
            'current_period_end' => '2026-03-01T00:00:00Z',
        ],
    );

    $handler = app(PolarSubscriptionLifecycleHandler::class);
    $result = $handler->canceled($webhook);

    expect($result)->not->toBeNull();
    expect($result['subscription']->id)->toBe($subscription->id);

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Canceled);
});

it('returns null for canceled when subscription not found', function (): void {
    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionCanceled,
        data: ['id' => 'sub_nonexistent'],
    );

    $handler = app(PolarSubscriptionLifecycleHandler::class);

    expect($handler->canceled($webhook))->toBeNull();
});

it('handles uncanceled subscription lifecycle', function (): void {
    $subscription = Subscription::factory()->create([
        'polar_subscription_id' => 'sub_uncancel',
        'status' => SubscriptionStatus::Canceled,
    ]);

    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionUncanceled,
        data: ['id' => 'sub_uncancel'],
    );

    $handler = app(PolarSubscriptionLifecycleHandler::class);
    $result = $handler->uncanceled($webhook);

    expect($result)->not->toBeNull();

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Active);
});

it('returns null for uncanceled when subscription not found', function (): void {
    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionUncanceled,
        data: ['id' => 'sub_nonexistent'],
    );

    $handler = app(PolarSubscriptionLifecycleHandler::class);

    expect($handler->uncanceled($webhook))->toBeNull();
});

it('handles revoked subscription lifecycle', function (): void {
    Plan::factory()->create(['tier' => PlanTier::Foundation->value]);

    $subscription = Subscription::factory()->create([
        'polar_subscription_id' => 'sub_revoke',
        'status' => SubscriptionStatus::Active,
    ]);

    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionRevoked,
        data: ['id' => 'sub_revoke'],
    );

    $handler = app(PolarSubscriptionLifecycleHandler::class);
    $result = $handler->revoked($webhook);

    expect($result)->not->toBeNull();

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Revoked);
});

it('returns null for revoked when subscription not found', function (): void {
    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionRevoked,
        data: ['id' => 'sub_nonexistent'],
    );

    $handler = app(PolarSubscriptionLifecycleHandler::class);

    expect($handler->revoked($webhook))->toBeNull();
});
