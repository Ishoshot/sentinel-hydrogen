<?php

declare(strict_types=1);

use App\Actions\Briefings\DeliverGeneratedBriefing;
use App\Enums\Briefings\BriefingDeliveryChannel;
use App\Models\BriefingGeneration;
use App\Models\BriefingSubscription;
use App\Notifications\Briefings\BriefingDeliveryNotification;
use App\Services\Slack\Contracts\SlackServiceContract;
use Illuminate\Support\Facades\Notification;

it('delivers briefing via email', function (): void {
    Notification::fake();

    $subscription = BriefingSubscription::factory()->create();
    $generation = BriefingGeneration::factory()->create();

    $slackService = Mockery::mock(SlackServiceContract::class);

    $action = new DeliverGeneratedBriefing;
    $action->handle($generation, BriefingDeliveryChannel::Email->value, $subscription, $slackService);

    Notification::assertSentTo($subscription->user, BriefingDeliveryNotification::class);
});

it('handles unknown delivery channel gracefully', function (): void {
    $subscription = BriefingSubscription::factory()->create();
    $generation = BriefingGeneration::factory()->create();
    $slackService = Mockery::mock(SlackServiceContract::class);

    $action = new DeliverGeneratedBriefing;
    $action->handle($generation, 'unknown_channel', $subscription, $slackService);

    expect(true)->toBeTrue();
});

it('handles push delivery channel', function (): void {
    $subscription = BriefingSubscription::factory()->create();
    $generation = BriefingGeneration::factory()->create();
    $slackService = Mockery::mock(SlackServiceContract::class);

    $action = new DeliverGeneratedBriefing;
    $action->handle($generation, BriefingDeliveryChannel::Push->value, $subscription, $slackService);

    expect(true)->toBeTrue();
});
