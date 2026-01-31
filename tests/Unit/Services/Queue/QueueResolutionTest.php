<?php

declare(strict_types=1);

use App\Enums\Queue;
use App\Services\Queue\QueueResolution;

it('creates forced queue resolution', function (): void {
    $resolution = new QueueResolution(
        queue: Queue::System,
        forcedBy: 'TestRule',
        reason: 'Forced by test rule',
        trace: [['rule' => 'TestRule', 'action' => 'force']],
        scores: null
    );

    expect($resolution->queue)->toBe(Queue::System)
        ->and($resolution->forcedBy)->toBe('TestRule')
        ->and($resolution->reason)->toBe('Forced by test rule')
        ->and($resolution->wasForced())->toBeTrue()
        ->and($resolution->queueName())->toBe('system');
});

it('creates scored queue resolution', function (): void {
    $resolution = new QueueResolution(
        queue: Queue::Default,
        forcedBy: null,
        reason: 'Selected based on scoring',
        trace: [['rule' => 'TestRule', 'scores' => ['default' => 10]]],
        scores: ['default' => 10, 'system' => 5]
    );

    expect($resolution->queue)->toBe(Queue::Default)
        ->and($resolution->forcedBy)->toBeNull()
        ->and($resolution->wasForced())->toBeFalse()
        ->and($resolution->scores)->toBe(['default' => 10, 'system' => 5]);
});

it('converts resolution to array', function (): void {
    $resolution = new QueueResolution(
        queue: Queue::System,
        forcedBy: 'SystemJobRule',
        reason: 'System job detected',
        trace: [['rule' => 'SystemJobRule', 'action' => 'force', 'queue' => 'system']],
        scores: null
    );

    $array = $resolution->toArray();

    expect($array)->toBe([
        'queue' => 'system',
        'forced_by' => 'SystemJobRule',
        'reason' => 'System job detected',
        'trace' => [['rule' => 'SystemJobRule', 'action' => 'force', 'queue' => 'system']],
        'scores' => null,
    ]);
});

it('defaults to empty trace and null scores', function (): void {
    $resolution = new QueueResolution(
        queue: Queue::Default,
        forcedBy: null,
        reason: 'Default queue'
    );

    expect($resolution->trace)->toBe([])
        ->and($resolution->scores)->toBeNull();
});
