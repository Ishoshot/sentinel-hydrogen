<?php

declare(strict_types=1);

use App\Enums\Queue\Queue;
use App\Models\Workspace;
use App\Services\Queue\ValueObjects\JobContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can be constructed with all parameters', function (): void {
    $context = new JobContext(
        jobClass: 'App\Jobs\ProcessReview',
        tier: 'illuminate',
        workspaceId: 123,
        isSystemJob: false,
        isUserInitiated: true,
        importance: 'high',
        estimatedDurationSeconds: 60,
        metadata: ['key' => 'value'],
    );

    expect($context->jobClass)->toBe('App\Jobs\ProcessReview');
    expect($context->tier)->toBe('illuminate');
    expect($context->workspaceId)->toBe(123);
    expect($context->isSystemJob)->toBeFalse();
    expect($context->isUserInitiated)->toBeTrue();
    expect($context->importance)->toBe('high');
    expect($context->estimatedDurationSeconds)->toBe(60);
    expect($context->metadata)->toBe(['key' => 'value']);
});

it('can be constructed with default values', function (): void {
    $context = new JobContext(jobClass: 'App\Jobs\TestJob');

    expect($context->jobClass)->toBe('App\Jobs\TestJob');
    expect($context->tier)->toBeNull();
    expect($context->workspaceId)->toBeNull();
    expect($context->isSystemJob)->toBeFalse();
    expect($context->isUserInitiated)->toBeFalse();
    expect($context->importance)->toBe('normal');
    expect($context->estimatedDurationSeconds)->toBeNull();
    expect($context->metadata)->toBe([]);
});

it('creates context for workspace', function (): void {
    $workspace = Workspace::factory()->create();

    $context = JobContext::forWorkspace(
        jobClass: 'App\Jobs\ReviewJob',
        workspace: $workspace,
        isUserInitiated: true,
        importance: 'high',
        estimatedDurationSeconds: 120,
        metadata: ['custom' => 'data'],
    );

    expect($context->jobClass)->toBe('App\Jobs\ReviewJob');
    expect($context->workspaceId)->toBe($workspace->id);
    expect($context->isSystemJob)->toBeFalse();
    expect($context->isUserInitiated)->toBeTrue();
    expect($context->importance)->toBe('high');
    expect($context->estimatedDurationSeconds)->toBe(120);
    expect($context->metadata)->toHaveKey('custom');
});

it('creates context for system job', function (): void {
    $context = JobContext::forSystemJob(
        jobClass: 'App\Jobs\SystemCleanup',
        importance: 'critical',
        estimatedDurationSeconds: 300,
        metadata: ['source' => 'scheduler'],
    );

    expect($context->jobClass)->toBe('App\Jobs\SystemCleanup');
    expect($context->tier)->toBeNull();
    expect($context->workspaceId)->toBeNull();
    expect($context->isSystemJob)->toBeTrue();
    expect($context->isUserInitiated)->toBeFalse();
    expect($context->importance)->toBe('critical');
    expect($context->estimatedDurationSeconds)->toBe(300);
    expect($context->metadata)->toBe(['source' => 'scheduler']);
});

it('creates context for webhook', function (): void {
    $context = JobContext::forWebhook(
        jobClass: 'App\Jobs\ProcessWebhook',
        workspaceId: 42,
        metadata: ['event' => 'push'],
    );

    expect($context->jobClass)->toBe('App\Jobs\ProcessWebhook');
    expect($context->tier)->toBeNull();
    expect($context->workspaceId)->toBe(42);
    expect($context->isSystemJob)->toBeFalse();
    expect($context->isUserInitiated)->toBeFalse();
    expect($context->importance)->toBe('high');
    expect($context->estimatedDurationSeconds)->toBe(30);
    expect($context->metadata)->toHaveKey('source');
    expect($context->metadata['source'])->toBe('webhook');
    expect($context->metadata['event'])->toBe('push');
});

it('checks if paid tier for illuminate', function (): void {
    $context = new JobContext(jobClass: 'App\Jobs\Test', tier: 'illuminate');

    expect($context->isPaidTier())->toBeTrue();
});

it('checks if paid tier for orchestrate', function (): void {
    $context = new JobContext(jobClass: 'App\Jobs\Test', tier: 'orchestrate');

    expect($context->isPaidTier())->toBeTrue();
});

it('checks if paid tier for sanctum', function (): void {
    $context = new JobContext(jobClass: 'App\Jobs\Test', tier: 'sanctum');

    expect($context->isPaidTier())->toBeTrue();
});

it('checks if not paid tier for spark', function (): void {
    $context = new JobContext(jobClass: 'App\Jobs\Test', tier: 'spark');

    expect($context->isPaidTier())->toBeFalse();
});

it('checks if enterprise tier', function (): void {
    $sanctum = new JobContext(jobClass: 'App\Jobs\Test', tier: 'sanctum');
    $other = new JobContext(jobClass: 'App\Jobs\Test', tier: 'illuminate');

    expect($sanctum->isEnterpriseTier())->toBeTrue();
    expect($other->isEnterpriseTier())->toBeFalse();
});

it('checks if priority queue is enabled', function (): void {
    $enabled = new JobContext(
        jobClass: 'App\Jobs\Test',
        metadata: ['priority_queue' => true],
    );
    $disabled = new JobContext(
        jobClass: 'App\Jobs\Test',
        metadata: ['priority_queue' => false],
    );
    $missing = new JobContext(jobClass: 'App\Jobs\Test');

    expect($enabled->isPriorityQueueEnabled())->toBeTrue();
    expect($disabled->isPriorityQueueEnabled())->toBeFalse();
    expect($missing->isPriorityQueueEnabled())->toBeFalse();
});

it('checks if critical importance', function (): void {
    $critical = new JobContext(jobClass: 'App\Jobs\Test', importance: 'critical');
    $normal = new JobContext(jobClass: 'App\Jobs\Test', importance: 'normal');

    expect($critical->isCritical())->toBeTrue();
    expect($normal->isCritical())->toBeFalse();
});

it('checks if long running', function (): void {
    $long = new JobContext(
        jobClass: 'App\Jobs\Test',
        estimatedDurationSeconds: 180,
    );
    $short = new JobContext(
        jobClass: 'App\Jobs\Test',
        estimatedDurationSeconds: 60,
    );
    $exact = new JobContext(
        jobClass: 'App\Jobs\Test',
        estimatedDurationSeconds: 120,
    );
    $null = new JobContext(jobClass: 'App\Jobs\Test');

    expect($long->isLongRunning())->toBeTrue();
    expect($short->isLongRunning())->toBeFalse();
    expect($exact->isLongRunning())->toBeFalse();
    expect($null->isLongRunning())->toBeFalse();
});

it('gets default queue for system job', function (): void {
    $context = new JobContext(jobClass: 'App\Jobs\Test', isSystemJob: true);

    expect($context->getDefaultQueue())->toBe(Queue::System);
});

it('gets default queue for regular job', function (): void {
    $context = new JobContext(jobClass: 'App\Jobs\Test', isSystemJob: false);

    expect($context->getDefaultQueue())->toBe(Queue::Default);
});

it('gets metadata value', function (): void {
    $context = new JobContext(
        jobClass: 'App\Jobs\Test',
        metadata: ['key' => 'value'],
    );

    expect($context->getMeta('key'))->toBe('value');
    expect($context->getMeta('missing'))->toBeNull();
    expect($context->getMeta('missing', 'default'))->toBe('default');
});

it('creates copy with additional metadata', function (): void {
    $original = new JobContext(
        jobClass: 'App\Jobs\Test',
        tier: 'illuminate',
        metadata: ['original' => 'value'],
    );

    $copy = $original->withMetadata(['new' => 'data']);

    expect($original->metadata)->toBe(['original' => 'value']);
    expect($copy->metadata)->toBe(['original' => 'value', 'new' => 'data']);
    expect($copy->jobClass)->toBe('App\Jobs\Test');
    expect($copy->tier)->toBe('illuminate');
});

it('converts to array', function (): void {
    $context = new JobContext(
        jobClass: 'App\Jobs\Test',
        tier: 'illuminate',
        workspaceId: 123,
        isSystemJob: false,
        isUserInitiated: true,
        importance: 'high',
        estimatedDurationSeconds: 60,
        metadata: ['key' => 'value'],
    );

    $array = $context->toArray();

    expect($array)->toBe([
        'job_class' => 'App\Jobs\Test',
        'tier' => 'illuminate',
        'workspace_id' => 123,
        'is_system_job' => false,
        'is_user_initiated' => true,
        'importance' => 'high',
        'estimated_duration_seconds' => 60,
        'metadata' => ['key' => 'value'],
    ]);
});
