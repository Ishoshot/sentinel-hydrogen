<?php

declare(strict_types=1);

use App\Actions\Briefings\GenerateBriefing;
use App\Enums\Briefings\BriefingSchedulePreset;
use App\Jobs\Briefings\GenerateScheduledBriefings;
use App\Models\Briefing;
use App\Models\BriefingGeneration;
use App\Models\BriefingSubscription;
use App\Models\Repository;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Briefings\BriefingLimitEnforcer;
use App\Services\Briefings\BriefingParameterValidator;
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
    $job->handle(
        app(GenerateBriefing::class),
        app(BriefingLimitEnforcer::class),
        app(BriefingParameterValidator::class),
    );

    $subscription->refresh();

    expect(BriefingGeneration::query()->count())->toBe(0)
        ->and($subscription->next_scheduled_at->greaterThan(now()))->toBeTrue();
});
