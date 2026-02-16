<?php

declare(strict_types=1);

use App\Enums\Briefings\BriefingDeliveryChannel;

it('returns all values', function (): void {
    $values = BriefingDeliveryChannel::values();

    expect($values)->toBeArray()
        ->toContain('push')
        ->toContain('email')
        ->toContain('slack');
});

it('returns correct labels', function (): void {
    expect(BriefingDeliveryChannel::Push->label())->toBe('Push Notification');
    expect(BriefingDeliveryChannel::Email->label())->toBe('Email');
    expect(BriefingDeliveryChannel::Slack->label())->toBe('Slack');
});
