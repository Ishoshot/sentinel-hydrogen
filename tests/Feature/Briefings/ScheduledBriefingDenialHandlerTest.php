<?php

declare(strict_types=1);

use App\Actions\Briefings\Handlers\ScheduledBriefingDenialHandler;
use App\Models\BriefingSubscription;
use App\Services\Briefings\ValueObjects\BriefingLimitResult;

it('defers subscription when reason is empty', function (): void {
    $subscription = BriefingSubscription::factory()->create([
        'next_scheduled_at' => now(),
    ]);
    $originalSchedule = $subscription->next_scheduled_at;

    $result = new BriefingLimitResult(allowed: false, reason: '');

    $handler = new ScheduledBriefingDenialHandler;
    $handler->handle($subscription, $result);

    $subscription->refresh();
    expect($subscription->next_scheduled_at->gt($originalSchedule))->toBeTrue();
});

it('defers subscription when reason does not contain currently generating', function (): void {
    $subscription = BriefingSubscription::factory()->create([
        'next_scheduled_at' => now(),
    ]);
    $originalSchedule = $subscription->next_scheduled_at;

    $result = new BriefingLimitResult(allowed: false, reason: 'rate limit exceeded');

    $handler = new ScheduledBriefingDenialHandler;
    $handler->handle($subscription, $result);

    $subscription->refresh();
    expect($subscription->next_scheduled_at->gt($originalSchedule))->toBeTrue();
});

it('does not defer when reason contains currently generating', function (): void {
    $subscription = BriefingSubscription::factory()->create([
        'next_scheduled_at' => now()->addHour(),
    ]);
    $originalSchedule = $subscription->next_scheduled_at->toISOString();

    $result = new BriefingLimitResult(allowed: false, reason: 'currently generating');

    $handler = new ScheduledBriefingDenialHandler;
    $handler->handle($subscription, $result);

    $subscription->refresh();
    expect($subscription->next_scheduled_at->toISOString())->toBe($originalSchedule);
});
