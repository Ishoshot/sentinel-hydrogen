<?php

declare(strict_types=1);

use App\Enums\Queue;

it('returns queues by priority', function (): void {
    $queues = Queue::byPriority();

    expect($queues)->toBeArray()
        ->and($queues[0])->toBe(Queue::System)
        ->and($queues[1])->toBe(Queue::Webhooks);
});

it('returns all queue names', function (): void {
    $names = Queue::allNames();

    expect($names)->toBeArray()
        ->toContain('system')
        ->toContain('webhooks')
        ->toContain('reviews-enterprise')
        ->toContain('reviews-paid')
        ->toContain('reviews-default')
        ->toContain('annotations')
        ->toContain('notifications')
        ->toContain('sync')
        ->toContain('default')
        ->toContain('long-running')
        ->toContain('bulk');
});

it('returns correct review queue for tier', function (): void {
    expect(Queue::reviewQueueForTier('enterprise'))->toBe(Queue::ReviewsEnterprise);
    expect(Queue::reviewQueueForTier('paid'))->toBe(Queue::ReviewsPaid);
    expect(Queue::reviewQueueForTier('pro'))->toBe(Queue::ReviewsPaid);
    expect(Queue::reviewQueueForTier('team'))->toBe(Queue::ReviewsPaid);
    expect(Queue::reviewQueueForTier('free'))->toBe(Queue::ReviewsDefault);
    expect(Queue::reviewQueueForTier('unknown'))->toBe(Queue::ReviewsDefault);
});

it('returns correct priorities', function (): void {
    expect(Queue::System->priority())->toBe(1);
    expect(Queue::Webhooks->priority())->toBe(5);
    expect(Queue::ReviewsEnterprise->priority())->toBe(20);
    expect(Queue::ReviewsPaid->priority())->toBe(30);
    expect(Queue::ReviewsDefault->priority())->toBe(40);
    expect(Queue::Annotations->priority())->toBe(50);
    expect(Queue::Notifications->priority())->toBe(55);
    expect(Queue::Sync->priority())->toBe(70);
    expect(Queue::Default->priority())->toBe(80);
    expect(Queue::LongRunning->priority())->toBe(90);
    expect(Queue::Bulk->priority())->toBe(100);
});

it('returns correct labels', function (): void {
    expect(Queue::System->label())->toBe('System');
    expect(Queue::Webhooks->label())->toBe('Webhooks');
    expect(Queue::ReviewsEnterprise->label())->toBe('Reviews (Enterprise)');
    expect(Queue::ReviewsPaid->label())->toBe('Reviews (Paid)');
    expect(Queue::ReviewsDefault->label())->toBe('Reviews (Default)');
    expect(Queue::Annotations->label())->toBe('Annotations');
    expect(Queue::Notifications->label())->toBe('Notifications');
    expect(Queue::Sync->label())->toBe('Sync');
    expect(Queue::Default->label())->toBe('Default');
    expect(Queue::LongRunning->label())->toBe('Long Running');
    expect(Queue::Bulk->label())->toBe('Bulk Operations');
});

it('returns correct timeouts', function (): void {
    expect(Queue::System->timeout())->toBe(60);
    expect(Queue::Webhooks->timeout())->toBe(30);
    expect(Queue::ReviewsEnterprise->timeout())->toBe(300);
    expect(Queue::ReviewsPaid->timeout())->toBe(300);
    expect(Queue::ReviewsDefault->timeout())->toBe(300);
    expect(Queue::Annotations->timeout())->toBe(60);
    expect(Queue::Notifications->timeout())->toBe(30);
    expect(Queue::Sync->timeout())->toBe(120);
    expect(Queue::Default->timeout())->toBe(60);
    expect(Queue::LongRunning->timeout())->toBe(600);
    expect(Queue::Bulk->timeout())->toBe(900);
});

it('returns correct tries', function (): void {
    expect(Queue::System->tries())->toBe(3);
    expect(Queue::Webhooks->tries())->toBe(5);
    expect(Queue::ReviewsEnterprise->tries())->toBe(3);
    expect(Queue::ReviewsPaid->tries())->toBe(3);
    expect(Queue::ReviewsDefault->tries())->toBe(3);
    expect(Queue::Annotations->tries())->toBe(3);
    expect(Queue::Notifications->tries())->toBe(3);
    expect(Queue::Sync->tries())->toBe(3);
    expect(Queue::Default->tries())->toBe(3);
    expect(Queue::LongRunning->tries())->toBe(2);
    expect(Queue::Bulk->tries())->toBe(2);
});

it('identifies review queues', function (): void {
    expect(Queue::ReviewsEnterprise->isReviewQueue())->toBeTrue();
    expect(Queue::ReviewsPaid->isReviewQueue())->toBeTrue();
    expect(Queue::ReviewsDefault->isReviewQueue())->toBeTrue();
    expect(Queue::System->isReviewQueue())->toBeFalse();
    expect(Queue::Webhooks->isReviewQueue())->toBeFalse();
});

it('identifies high priority queues', function (): void {
    expect(Queue::System->isHighPriority())->toBeTrue();
    expect(Queue::Webhooks->isHighPriority())->toBeTrue();
    expect(Queue::ReviewsEnterprise->isHighPriority())->toBeFalse();
    expect(Queue::Default->isHighPriority())->toBeFalse();
});

it('identifies long running queues', function (): void {
    expect(Queue::LongRunning->isLongRunning())->toBeTrue();
    expect(Queue::Bulk->isLongRunning())->toBeTrue();
    expect(Queue::System->isLongRunning())->toBeFalse();
    expect(Queue::Default->isLongRunning())->toBeFalse();
});
