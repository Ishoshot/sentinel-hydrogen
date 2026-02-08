<?php

declare(strict_types=1);

use App\Enums\Briefings\BriefingGenerationStatus;
use App\Events\Briefings\BriefingGenerationCompleted;
use App\Events\Briefings\BriefingGenerationFailed;
use App\Events\Briefings\BriefingGenerationProgress;
use App\Events\Briefings\BriefingGenerationStarted;
use App\Models\Briefing;
use App\Models\BriefingGeneration;
use App\Models\Plan;
use App\Models\Workspace;
use Illuminate\Broadcasting\PrivateChannel;

beforeEach(function (): void {
    $this->plan = Plan::factory()->create();
    $this->workspace = Workspace::factory()->create(['plan_id' => $this->plan->id]);
    $this->briefing = Briefing::factory()->system()->create([
        'is_active' => true,
        'slug' => 'weekly-summary',
    ]);
});

// --- BriefingGenerationStarted ---

it('broadcasts started event on correct private channel', function (): void {
    $generation = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->pending()
        ->create();

    $event = new BriefingGenerationStarted($generation);
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe("private-workspace.{$this->workspace->id}.briefings");
});

it('broadcasts started event with correct name and payload', function (): void {
    $generation = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->pending()
        ->create();

    $event = new BriefingGenerationStarted($generation);

    expect($event->broadcastAs())->toBe('briefing.started');

    $data = $event->broadcastWith();
    expect($data)->toHaveKeys(['generation_id', 'briefing_id', 'status'])
        ->and($data['generation_id'])->toBe($generation->id)
        ->and($data['briefing_id'])->toBe($this->briefing->id)
        ->and($data['status'])->toBe(BriefingGenerationStatus::Pending->value);
});

// --- BriefingGenerationProgress ---

it('broadcasts progress event on correct private channel', function (): void {
    $generation = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->processing()
        ->create();

    $event = new BriefingGenerationProgress($generation, 50, 'Analyzing data...');
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe("private-workspace.{$this->workspace->id}.briefings");
});

it('broadcasts progress event with correct name and payload', function (): void {
    $generation = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->processing()
        ->create();

    $event = new BriefingGenerationProgress($generation, 65, 'Generating narrative...');

    expect($event->broadcastAs())->toBe('briefing.progress');

    $data = $event->broadcastWith();
    expect($data)->toHaveKeys(['generation_id', 'briefing_id', 'progress', 'message'])
        ->and($data['generation_id'])->toBe($generation->id)
        ->and($data['briefing_id'])->toBe($this->briefing->id)
        ->and($data['progress'])->toBe(65)
        ->and($data['message'])->toBe('Generating narrative...');
});

// --- BriefingGenerationCompleted ---

it('broadcasts completed event on correct private channel', function (): void {
    $generation = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->completed()
        ->create();

    $event = new BriefingGenerationCompleted($generation);
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe("private-workspace.{$this->workspace->id}.briefings");
});

it('broadcasts completed event with correct name and payload', function (): void {
    $generation = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->completed()
        ->create();

    $event = new BriefingGenerationCompleted($generation);

    expect($event->broadcastAs())->toBe('briefing.completed');

    $data = $event->broadcastWith();
    expect($data)->toHaveKeys(['generation_id', 'briefing_id', 'briefing_slug', 'status', 'has_achievements'])
        ->and($data['generation_id'])->toBe($generation->id)
        ->and($data['briefing_id'])->toBe($this->briefing->id)
        ->and($data['briefing_slug'])->toBe('weekly-summary')
        ->and($data['status'])->toBe(BriefingGenerationStatus::Completed->value)
        ->and($data['has_achievements'])->toBeTrue();
});

it('broadcasts completed event with has_achievements false when empty', function (): void {
    $generation = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->completed()
        ->create(['achievements' => null]);

    $event = new BriefingGenerationCompleted($generation);
    $data = $event->broadcastWith();

    expect($data['has_achievements'])->toBeFalse();
});

// --- BriefingGenerationFailed ---

it('broadcasts failed event on correct private channel', function (): void {
    $generation = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->failed()
        ->create();

    $event = new BriefingGenerationFailed($generation);
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe("private-workspace.{$this->workspace->id}.briefings");
});

it('broadcasts failed event with correct name and payload', function (): void {
    $generation = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->failed()
        ->create();

    $event = new BriefingGenerationFailed($generation);

    expect($event->broadcastAs())->toBe('briefing.failed');

    $data = $event->broadcastWith();
    expect($data)->toHaveKeys(['generation_id', 'briefing_id', 'status', 'error'])
        ->and($data['generation_id'])->toBe($generation->id)
        ->and($data['briefing_id'])->toBe($this->briefing->id)
        ->and($data['status'])->toBe(BriefingGenerationStatus::Failed->value)
        ->and($data['error'])->toBe('Failed to generate narrative: AI provider unavailable');
});

// --- ShouldBroadcastNow ---

it('implements ShouldBroadcastNow for all events', function (): void {
    $generation = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->pending()
        ->create();

    expect(new BriefingGenerationStarted($generation))
        ->toBeInstanceOf(Illuminate\Contracts\Broadcasting\ShouldBroadcastNow::class)
        ->and(new BriefingGenerationProgress($generation, 50, 'test'))
        ->toBeInstanceOf(Illuminate\Contracts\Broadcasting\ShouldBroadcastNow::class)
        ->and(new BriefingGenerationCompleted($generation))
        ->toBeInstanceOf(Illuminate\Contracts\Broadcasting\ShouldBroadcastNow::class)
        ->and(new BriefingGenerationFailed($generation))
        ->toBeInstanceOf(Illuminate\Contracts\Broadcasting\ShouldBroadcastNow::class);
});
