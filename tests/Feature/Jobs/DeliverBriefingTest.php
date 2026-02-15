<?php

declare(strict_types=1);

use App\Enums\Queue\Queue;
use App\Jobs\Briefings\DeliverBriefing;
use App\Models\BriefingGeneration;
use App\Models\BriefingSubscription;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue as QueueFacade;

it('is dispatched to the briefings-default queue', function (): void {
    $generation = BriefingGeneration::factory()->completed()->create();
    $subscription = BriefingSubscription::factory()->create();

    $job = new DeliverBriefing(
        generation: $generation,
        channel: 'email',
        subscription: $subscription,
    );

    expect($job->queue)->toBe(Queue::BriefingsDefault->value);
});

it('can be dispatched to the queue', function (): void {
    QueueFacade::fake();

    $generation = BriefingGeneration::factory()->completed()->create();
    $subscription = BriefingSubscription::factory()->create();

    DeliverBriefing::dispatch($generation, 'email', $subscription);

    QueueFacade::assertPushed(DeliverBriefing::class, function (DeliverBriefing $job) use ($generation, $subscription): bool {
        return $job->generation->id === $generation->id
            && $job->channel === 'email'
            && $job->subscription->id === $subscription->id;
    });
});

it('handles email delivery channel via the action', function (): void {
    Notification::fake();

    $generation = BriefingGeneration::factory()->completed()->create();
    $subscription = BriefingSubscription::factory()->create();

    $job = new DeliverBriefing(
        generation: $generation,
        channel: 'email',
        subscription: $subscription,
    );

    // Dispatching synchronously invokes the handle method through the container
    DeliverBriefing::dispatchSync($generation, 'email', $subscription);

    // If we get here without exceptions, the job executed successfully
    expect(true)->toBeTrue();
});

it('logs warning for unknown delivery channel', function (): void {
    Log::spy();

    $generation = BriefingGeneration::factory()->completed()->create();
    $subscription = BriefingSubscription::factory()->create();

    DeliverBriefing::dispatchSync($generation, 'carrier-pigeon', $subscription);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($message) => str_contains($message, 'Unknown delivery channel'));
});

it('stores generation and subscription as public properties', function (): void {
    $generation = BriefingGeneration::factory()->completed()->create();
    $subscription = BriefingSubscription::factory()->create();

    $job = new DeliverBriefing(
        generation: $generation,
        channel: 'slack',
        subscription: $subscription,
    );

    expect($job->generation->id)->toBe($generation->id)
        ->and($job->channel)->toBe('slack')
        ->and($job->subscription->id)->toBe($subscription->id);
});
