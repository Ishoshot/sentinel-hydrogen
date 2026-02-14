<?php

declare(strict_types=1);

use App\Actions\Briefings\ProcessScheduledBriefings;
use App\Enums\Briefings\BriefingSchedulePreset;
use App\Jobs\Briefings\DeliverBriefing;
use App\Jobs\Briefings\GenerateScheduledBriefings;
use App\Jobs\Briefings\ProcessBriefingGeneration;
use App\Models\Briefing;
use App\Models\BriefingGeneration;
use App\Models\BriefingSubscription;
use App\Models\Repository;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

it('defers scheduled briefings when parameters fail validation', function (): void {
    Queue::fake();

    config()->set('briefings.limits.max_repositories', 1);

    $workspace = Workspace::factory()->create();
    $user = User::factory()->create();
    $briefing = Briefing::factory()->system()->create([
        'is_active' => true,
    ]);

    $repoOne = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $repoTwo = Repository::factory()->create(['workspace_id' => $workspace->id]);

    $subscription = BriefingSubscription::factory()
        ->forWorkspace($workspace)
        ->forUser($user)
        ->create([
            'briefing_id' => $briefing->id,
            'schedule_preset' => BriefingSchedulePreset::Daily,
            'schedule_hour' => 9,
            'parameters' => [
                'repository_ids' => [$repoOne->id, $repoTwo->id],
            ],
            'next_scheduled_at' => now()->subMinute(),
            'is_active' => true,
        ]);

    $job = new GenerateScheduledBriefings();
    $job->handle(app(ProcessScheduledBriefings::class));

    $subscription->refresh();

    expect(BriefingGeneration::query()->count())->toBe(0)
        ->and($subscription->next_scheduled_at->greaterThan(now()))->toBeTrue();
});

it('generates due scheduled briefings and dispatches deliveries', function (): void {
    Queue::fake();
    config()->set('briefings.data_guard.enabled', false);

    $workspace = Workspace::factory()->create();
    $user = User::factory()->create();
    $briefing = Briefing::factory()->system()->create([
        'is_active' => true,
    ]);

    $subscription = BriefingSubscription::factory()
        ->forWorkspace($workspace)
        ->forUser($user)
        ->create([
            'briefing_id' => $briefing->id,
            'schedule_preset' => BriefingSchedulePreset::Daily,
            'schedule_hour' => 9,
            'parameters' => [],
            'delivery_channels' => ['email', 'slack'],
            'next_scheduled_at' => now()->subMinute(),
            'is_active' => true,
        ]);

    $job = new GenerateScheduledBriefings();
    $job->handle(app(ProcessScheduledBriefings::class));

    $subscription->refresh();
    $generation = BriefingGeneration::query()->first();

    expect($generation)->not->toBeNull()
        ->and($subscription->last_generated_at)->not->toBeNull()
        ->and($subscription->next_scheduled_at->greaterThan(now()))->toBeTrue();

    Queue::assertPushed(ProcessBriefingGeneration::class, 1);
    Queue::assertPushed(DeliverBriefing::class, 2);
});
