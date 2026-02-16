<?php

declare(strict_types=1);

use App\Actions\Billing\Resolvers\PolarSubscriptionLookupResolver;
use App\Enums\Webhooks\PolarWebhookEvent;
use App\Models\Subscription;
use App\Services\Billing\ValueObjects\VerifiedPolarWebhook;

it('resolves subscription from lifecycle payload', function (): void {
    $subscription = Subscription::factory()->create([
        'polar_subscription_id' => 'sub_123',
    ]);

    $resolver = new PolarSubscriptionLookupResolver;
    $result = $resolver->fromLifecyclePayload(['id' => 'sub_123'], 'canceled');

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($subscription->id);
});

it('returns null for empty lifecycle payload', function (): void {
    $resolver = new PolarSubscriptionLookupResolver;

    expect($resolver->fromLifecyclePayload([], 'canceled'))->toBeNull();
});

it('returns null for missing subscription id in lifecycle payload', function (): void {
    $resolver = new PolarSubscriptionLookupResolver;

    expect($resolver->fromLifecyclePayload(['other_key' => 'value'], 'canceled'))->toBeNull();
});

it('returns null when subscription not found in lifecycle payload', function (): void {
    $resolver = new PolarSubscriptionLookupResolver;

    expect($resolver->fromLifecyclePayload(['id' => 'sub_nonexistent'], 'canceled'))->toBeNull();
});

it('resolves subscription from webhook', function (): void {
    $subscription = Subscription::factory()->create([
        'polar_subscription_id' => 'sub_456',
    ]);

    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionUpdated,
        data: ['subscription' => ['id' => 'sub_456']],
    );

    $resolver = new PolarSubscriptionLookupResolver;
    $result = $resolver->fromWebhook($webhook);

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($subscription->id);
});

it('returns null for empty webhook data', function (): void {
    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionUpdated,
        data: [],
    );

    $resolver = new PolarSubscriptionLookupResolver;

    expect($resolver->fromWebhook($webhook))->toBeNull();
});

it('returns null when subscription not found from webhook', function (): void {
    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionUpdated,
        data: ['id' => 'sub_nonexistent'],
    );

    $resolver = new PolarSubscriptionLookupResolver;

    expect($resolver->fromWebhook($webhook))->toBeNull();
});

it('returns null when webhook has no subscription id', function (): void {
    $webhook = new VerifiedPolarWebhook(
        type: PolarWebhookEvent::SubscriptionUpdated,
        data: ['some_other_key' => 'value'],
    );

    $resolver = new PolarSubscriptionLookupResolver;

    expect($resolver->fromWebhook($webhook))->toBeNull();
});
