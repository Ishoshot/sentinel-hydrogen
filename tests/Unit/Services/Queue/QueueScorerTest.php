<?php

declare(strict_types=1);

use App\Enums\Queue\Queue;
use App\Services\Queue\QueueScorer;

it('initializes scores with inverted priorities', function (): void {
    $scorer = new QueueScorer();
    $scores = $scorer->initializeScores();

    // System (priority 1) should have the highest initial score
    expect($scores[Queue::System->value])->toBe(99);
    // Bulk (priority 100) should have the lowest initial score
    expect($scores[Queue::Bulk->value])->toBe(0);
    // Every queue case should be represented
    expect(count($scores))->toBe(count(Queue::cases()));
});

it('selects queue with highest score', function (): void {
    $scorer = new QueueScorer();
    $scores = $scorer->initializeScores();

    // Boost ReviewsPaid well above the initial leader
    $scores[Queue::ReviewsPaid->value] += 200;

    expect($scorer->selectByScore($scores))->toBe(Queue::ReviewsPaid);
});

it('breaks ties using lowest base priority number', function (): void {
    $scorer = new QueueScorer();

    // Give two queues the same score
    $scores = [];
    foreach (Queue::cases() as $queue) {
        $scores[$queue->value] = 0;
    }
    $scores[Queue::Webhooks->value] = 50;     // priority 5
    $scores[Queue::ReviewsPaid->value] = 50;  // priority 30

    // Webhooks has lower priority number (higher inherent priority) → wins tie
    expect($scorer->selectByScore($scores))->toBe(Queue::Webhooks);
});
